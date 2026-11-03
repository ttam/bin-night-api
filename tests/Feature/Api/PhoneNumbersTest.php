<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\VerificationResult;
use App\Models\PhoneNumber;
use App\Services\Verification\Exceptions\VerificationUnavailableException;
use App\Services\Verification\FakeVerifier;
use App\Services\Verification\Interfaces\PhoneVerifierInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

final class PhoneNumbersTest extends ApiTestCase
{
    public function testAVerifiedNumberComesStraightBackWithoutACode(): void
    {
        $verifier = $this->spyOnVerifier();
        $id = $this->phoneNumber('+61400000000', 'verified');

        $this->postJson('/api/v1/phone-numbers', ['number' => '+61400000000'])->assertOk()
            ->assertExactJson(['data' => ['id' => $id, 'number' => '+61400000000', 'status' => 'verified']]);

        self::assertSame([], $verifier->enrolled);
    }

    public function testANewNumberIsPendingAndGetsACode(): void
    {
        $verifier = $this->spyOnVerifier();

        $response = $this->postJson('/api/v1/phone-numbers', ['number' => '+61400000000'])->assertCreated()
            ->assertJsonPath('data.number', '+61400000000')
            ->assertJsonPath('data.status', 'pending');

        self::assertSame(['+61400000000'], $verifier->enrolled);
        self::assertSame($response->json('data.id'), DB::table('phone_numbers')->value('id'));
        self::assertSame($this->userId, DB::table('phone_numbers')->value('user_id'));
    }

    public function testAskingAgainForAPendingNumberReusesItAndSendsAnotherCode(): void
    {
        $verifier = $this->spyOnVerifier();

        $first = $this->postJson('/api/v1/phone-numbers', ['number' => '+61400000000'])->json('data.id');
        $second = $this->postJson('/api/v1/phone-numbers', ['number' => '+61400000000'])->json('data.id');

        self::assertSame($first, $second);
        self::assertSame(1, DB::table('phone_numbers')->count());
        self::assertCount(2, $verifier->enrolled);
    }

    public function testTwoUsersMayHoldTheSameNumber(): void
    {
        $this->phoneNumber('+61400000000', 'verified', $this->createUser('user_222'));

        $this->postJson('/api/v1/phone-numbers', ['number' => '+61400000000'])->assertCreated()->assertJsonPath('data.status', 'pending');

        self::assertSame(2, DB::table('phone_numbers')->count());
    }

    /** @return iterable<string, array{mixed}> */
    public static function badNumbers(): iterable
    {
        yield 'a landline' => ['+61290000000'];
        yield 'a New Zealand mobile' => ['+64211234567'];
        yield 'without the plus' => ['61400000000'];
        yield 'local format' => ['0400000000'];
        yield 'too short' => ['+6140000000'];
        yield 'too long' => ['+614000000000'];
        yield 'with spaces' => ['+61 400 000 000'];
        yield 'a number' => [61400000000];
        yield 'empty' => [''];
        yield 'null' => [null];
    }

    /** @dataProvider badNumbers */
    #[\PHPUnit\Framework\Attributes\DataProvider('badNumbers')]
    public function testOnlyAustralianMobilesAreAccepted(mixed $number): void
    {
        $verifier = $this->spyOnVerifier();

        $this->postJson('/api/v1/phone-numbers', ['number' => $number])->assertUnprocessable()->assertJsonValidationErrors('number');

        self::assertSame([], $verifier->enrolled);
        self::assertSame(0, DB::table('phone_numbers')->count());
    }

    public function testTheMessageForABadNumberIsPlain(): void
    {
        $this->postJson('/api/v1/phone-numbers', ['number' => '0400000000'])
            ->assertJsonPath('errors.number.0', 'The number must be an Australian mobile, like +61400000000.');
    }

    public function testTheListShowsOnlyTheUsersOwnNumbersOldestFirst(): void
    {
        $this->phoneNumber('+61400000002', 'pending', null, '1985-10-26 01:22:00');
        $this->phoneNumber('+61400000001', 'verified', null, '1985-10-26 01:21:00');
        $this->phoneNumber('+61400000009', 'verified', $this->createUser('user_222'));

        $this->getJson('/api/v1/phone-numbers')->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.number', '+61400000001')
            ->assertJsonPath('data.1.number', '+61400000002');
    }

    public function testTheRightCodeVerifiesTheNumber(): void
    {
        $id = $this->phoneNumber('+61400000000', 'pending');

        $this->postJson("/api/v1/phone-numbers/{$id}/verify", ['code' => FakeVerifier::CODE])->assertOk()
            ->assertJsonPath('data.status', 'verified');

        self::assertSame('verified', DB::table('phone_numbers')->where('id', $id)->value('status'));
        self::assertNotNull(DB::table('phone_numbers')->where('id', $id)->value('verified_at'));
    }

    public function testAWrongCodeIsRefusedInPlainWords(): void
    {
        $id = $this->phoneNumber('+61400000000', 'pending');

        $this->postJson("/api/v1/phone-numbers/{$id}/verify", ['code' => '000000'])->assertUnprocessable()
            ->assertExactJson([
                'errors' => ['code' => ['That code is not right. Check it and try again.']],
                'message' => 'That code is not right. Check it and try again.',
            ]);

        self::assertSame('pending', DB::table('phone_numbers')->where('id', $id)->value('status'));
    }

    /** @return iterable<string, array{VerificationResult, string}> */
    public static function refusals(): iterable
    {
        yield 'expired' => [VerificationResult::EXPIRED, 'That code has expired. Ask for a new one.'];
        yield 'too many attempts' => [VerificationResult::TOO_MANY_ATTEMPTS, 'Too many wrong codes. Ask for a new one.'];
        yield 'unavailable' => [VerificationResult::UNAVAILABLE, 'We could not check the code just now. Try again in a moment.'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('refusals')]
    public function testEveryOtherAnswerIsRefusedWithItsOwnMessage(VerificationResult $result, string $message): void
    {
        $verifier = $this->createStub(PhoneVerifierInterface::class);
        $verifier->method('check')->willReturn($result);
        $this->app->instance(PhoneVerifierInterface::class, $verifier);
        $id = $this->phoneNumber('+61400000000', 'pending');

        $this->postJson("/api/v1/phone-numbers/{$id}/verify", ['code' => '123456'])->assertUnprocessable()->assertJsonPath('message', $message);
    }

    public function testCheckingANumberThatIsAlreadyVerifiedAsksNoOne(): void
    {
        $verifier = $this->createMock(PhoneVerifierInterface::class);
        $verifier->expects(self::never())->method('check');
        $this->app->instance(PhoneVerifierInterface::class, $verifier);
        $id = $this->phoneNumber('+61400000000', 'verified');

        $this->postJson("/api/v1/phone-numbers/{$id}/verify", ['code' => '000000'])->assertOk()->assertJsonPath('data.status', 'verified');
    }

    /** @return iterable<string, array{mixed}> */
    public static function badCodes(): iterable
    {
        yield 'missing' => [null];
        yield 'letters' => ['abcdef'];
        yield 'too short' => ['123'];
        yield 'too long' => ['12345678901'];
        yield 'a number' => [[123456]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badCodes')]
    public function testACodeMustLookLikeACode(mixed $code): void
    {
        $id = $this->phoneNumber('+61400000000', 'pending');

        $this->postJson("/api/v1/phone-numbers/{$id}/verify", ['code' => $code])->assertUnprocessable()->assertJsonValidationErrors('code');
    }

    public function testAnotherUsersNumberLooksLikeItDoesNotExist(): void
    {
        $id = $this->phoneNumber('+61400000000', 'pending', $this->createUser('user_222'));

        $this->postJson("/api/v1/phone-numbers/{$id}/verify", ['code' => FakeVerifier::CODE])->assertNotFound();
        $this->postJson("/api/v1/phone-numbers/{$id}/verification")->assertNotFound();

        self::assertSame('pending', DB::table('phone_numbers')->where('id', $id)->value('status'));
    }

    public function testACodeCanBeSentAgain(): void
    {
        $verifier = $this->spyOnVerifier();
        $id = $this->phoneNumber('+61400000000', 'pending');

        $this->postJson("/api/v1/phone-numbers/{$id}/verification")->assertOk()->assertJsonPath('data.status', 'pending');

        self::assertSame(['+61400000000'], $verifier->enrolled);
    }

    public function testNoCodeIsSentAgainForAVerifiedNumber(): void
    {
        $verifier = $this->spyOnVerifier();
        $id = $this->phoneNumber('+61400000000', 'verified');

        $this->postJson("/api/v1/phone-numbers/{$id}/verification")->assertOk()->assertJsonPath('data.status', 'verified');

        self::assertSame([], $verifier->enrolled);
    }

    public function testTheProviderBeingDownIsA503AndTheNumberStaysOutOfTheLog(): void
    {
        $verifier = $this->createStub(PhoneVerifierInterface::class);
        $verifier->method('enrol')->willThrowException(new VerificationUnavailableException('Twilio\Exceptions\RestException (code 20003)'));
        $this->app->instance(PhoneVerifierInterface::class, $verifier);
        $logged = [];
        \Illuminate\Support\Facades\Log::listen(static function ($event) use (&$logged): void {
            $logged[] = $event->message . \json_encode($event->context);
        });

        $this->postJson('/api/v1/phone-numbers', ['number' => '+61400000000'])->assertServiceUnavailable()
            ->assertJsonPath('message', 'We could not send a code just now. Try again in a moment.');

        self::assertNotSame([], $logged);
        self::assertStringNotContainsString('+61400000000', \implode('', $logged));
        self::assertStringContainsString('code 20003', \implode('', $logged));
    }

    public function testACodeCannotBeRequestedTooOftenForOneUser(): void
    {
        $this->spyOnVerifier();
        \config(['phone.code_limits.per_user_per_hour' => 2]);

        $this->postJson('/api/v1/phone-numbers', ['number' => '+61400000001'])->assertCreated();
        $this->postJson('/api/v1/phone-numbers', ['number' => '+61400000002'])->assertCreated();
        $this->postJson('/api/v1/phone-numbers', ['number' => '+61400000003'])->assertStatus(429)->assertHeader('Retry-After');
    }

    public function testACodeCannotBeRequestedTooOftenForOneNumberAcrossUsers(): void
    {
        $verifier = $this->spyOnVerifier();
        \config(['phone.code_limits.per_number_per_hour' => 2]);

        foreach (['user_222', 'user_333'] as $externalId) {
            \config(['authentication.clerk.development_user' => $externalId]);
            $this->postJson('/api/v1/phone-numbers', ['number' => '+61400000000'])->assertCreated();
        }

        \config(['authentication.clerk.development_user' => 'user_444']);
        $this->postJson('/api/v1/phone-numbers', ['number' => '+61400000000'])->assertStatus(429);

        self::assertCount(2, $verifier->enrolled);
    }

    public function testACodeCannotBeRequestedTooOftenFromOneAddress(): void
    {
        $this->spyOnVerifier();
        \config(['phone.code_limits.per_ip_per_hour' => 1]);

        $this->postJson('/api/v1/phone-numbers', ['number' => '+61400000001'])->assertCreated();

        foreach (['user_222', 'user_333'] as $externalId) {
            \config(['authentication.clerk.development_user' => $externalId]);
            $this->postJson('/api/v1/phone-numbers', ['number' => '+61400000002'])->assertStatus(429);
        }
    }

    public function testARefusedRequestDoesNotUseUpTheOtherLimits(): void
    {
        $this->spyOnVerifier();
        \config(['phone.code_limits.per_user_per_hour' => 1]);

        $this->postJson('/api/v1/phone-numbers', ['number' => '+61400000001'])->assertCreated();
        $this->postJson('/api/v1/phone-numbers', ['number' => '+61400000002'])->assertStatus(429);

        self::assertSame(1, RateLimiter::attempts('phone-code:number:+61400000001'));
        self::assertSame(0, RateLimiter::attempts('phone-code:number:+61400000002'));
    }

    public function testAVerifiedNumberDoesNotUseUpALimit(): void
    {
        $this->spyOnVerifier();
        \config(['phone.code_limits.per_user_per_hour' => 1]);
        $this->phoneNumber('+61400000000', 'verified');

        $this->postJson('/api/v1/phone-numbers', ['number' => '+61400000000'])->assertOk();
        $this->postJson('/api/v1/phone-numbers', ['number' => '+61400000000'])->assertOk();
        $this->postJson('/api/v1/phone-numbers', ['number' => '+61400000001'])->assertCreated();
    }

    public function testGuessingCodesIsThrottled(): void
    {
        $id = $this->phoneNumber('+61400000000', 'pending');

        for ($i = 0; $i < 30; ++$i) {
            $this->postJson("/api/v1/phone-numbers/{$id}/verify", ['code' => '000000'])->assertUnprocessable();
        }

        $this->postJson("/api/v1/phone-numbers/{$id}/verify", ['code' => '000000'])->assertStatus(429);
    }

    public function testItNeedsASignedInUser(): void
    {
        \config(['authentication.clerk.development_user' => null]);

        $this->getJson('/api/v1/phone-numbers')->assertUnauthorized();
        $this->postJson('/api/v1/phone-numbers', ['number' => '+61400000000'])->assertUnauthorized();
    }

    public function testDeletingAUserDeletesTheirNumbers(): void
    {
        $this->phoneNumber('+61400000000', 'verified');

        self::assertCount(1, \App\Models\User::query()->findOrFail($this->userId)->phoneNumbers);

        DB::table('users')->where('id', $this->userId)->delete();

        self::assertSame(0, PhoneNumber::query()->count());
    }

    private function phoneNumber(string $number, string $status, ?string $userId = null, string $createdAt = '1985-10-26 01:21:00'): string
    {
        $id = (string) \Illuminate\Support\Str::uuid();

        DB::table('phone_numbers')->insert([
            'id' => $id,
            'user_id' => $userId ?? $this->userId,
            'number' => $number,
            'status' => $status,
            'verified_at' => $status === 'verified' ? $createdAt : null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        return $id;
    }

    private function spyOnVerifier(): object
    {
        $verifier = new class() implements PhoneVerifierInterface {
            /** @var list<string> */
            public array $enrolled = [];

            public function check(string $number, string $code): VerificationResult
            {
                return VerificationResult::APPROVED;
            }

            public function enrol(string $number): void
            {
                $this->enrolled[] = $number;
            }
        };
        $this->app->instance(PhoneVerifierInterface::class, $verifier);

        return $verifier;
    }
}
