<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\VerificationResult;
use App\Services\Verification\Interfaces\PhoneVerifierInterface;
use Illuminate\Support\Facades\DB;

final class NotificationsTest extends ApiTestCase
{
    public function testCreateCountsTheNewNotificationAsActive(): void
    {
        $this->useLimits(notificationsPerHouse: 1);
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');

        $this->postJson('/api/v1/notifications', $this->validPayload($houseId))
            ->assertCreated()
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.name', 'Marty\'s Phone')
            ->assertJsonPath('data.send_time', '18:30:00')
            ->assertJsonPath('data.target', '+61400000000')
            ->assertJsonPath('data.type', 'sms')
            ->assertJsonPath('data.verified', false);

        self::assertSame(1, DB::table('notifications')->count());
    }

    public function testCreateForANewNumberMakesItPendingAndSendsNothing(): void
    {
        $verifier = $this->spyOnVerifier();
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');

        $response = $this->postJson('/api/v1/notifications', $this->validPayload($houseId))->assertCreated();

        self::assertSame(1, DB::table('phone_numbers')->count());
        self::assertSame('pending', DB::table('phone_numbers')->value('status'));
        self::assertSame($this->userId, DB::table('phone_numbers')->value('user_id'));
        self::assertSame(DB::table('phone_numbers')->value('id'), $response->json('data.phone_number_id'));
        self::assertSame(DB::table('phone_numbers')->value('id'), DB::table('notifications')->value('phone_number_id'));
        self::assertSame([], $verifier->enrolled);
    }

    public function testCreateForAVerifiedNumberIsVerifiedAtOnce(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $phoneNumberId = $this->createPhoneNumber('+61400000000');

        $this->postJson('/api/v1/notifications', $this->validPayload($houseId))->assertCreated()
            ->assertJsonPath('data.verified', true)
            ->assertJsonPath('data.phone_number_id', $phoneNumberId);

        self::assertSame(1, DB::table('phone_numbers')->count());
    }

    public function testNotificationsOnTheSameNumberShareIt(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');

        $this->postJson('/api/v1/notifications', $this->validPayload($houseId))->assertCreated();
        $this->postJson('/api/v1/notifications', [...$this->validPayload($houseId), 'name' => 'Second'])->assertCreated();

        self::assertSame(1, DB::table('phone_numbers')->count());
        self::assertSame(1, DB::table('notifications')->distinct()->count('phone_number_id'));
    }

    public function testAnotherUsersVerifiedNumberDoesNotVerifyMine(): void
    {
        $this->createPhoneNumber('+61400000000', userId: $this->createUser('user_222'));
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');

        $this->postJson('/api/v1/notifications', $this->validPayload($houseId))->assertCreated()->assertJsonPath('data.verified', false);

        self::assertSame(2, DB::table('phone_numbers')->count());
    }

    public function testVerifyingTheNumberVerifiesEveryNotificationUsingIt(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $this->postJson('/api/v1/notifications', $this->validPayload($houseId))->assertCreated();
        $this->postJson('/api/v1/notifications', [...$this->validPayload($houseId), 'name' => 'Second'])->assertCreated();
        $phoneNumberId = DB::table('phone_numbers')->value('id');

        $this->postJson("/api/v1/phone-numbers/{$phoneNumberId}/verify", ['code' => '123456'])->assertOk();

        $this->getJson('/api/v1/notifications')->assertOk()
            ->assertJsonPath('data.0.verified', true)
            ->assertJsonPath('data.1.verified', true);
    }

    public function testCreateInAnotherUsersHouseIsNotFound(): void
    {
        $houseId = $this->createHouse('Lyon Estates', '1985-10-26 01:21:00', $this->createUser('user_222'));

        $this->postJson('/api/v1/notifications', $this->validPayload($houseId))->assertNotFound();

        self::assertSame(0, DB::table('notifications')->count());
    }

    public function testCreateOverThePlanLimitIsRejected(): void
    {
        $this->useLimits(notificationsPerHouse: 1);
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $this->createNotification($houseId, '+61400000001', '1955-11-12 06:00:00');

        $this->postJson('/api/v1/notifications', $this->validPayload($houseId))
            ->assertForbidden()
            ->assertExactJson(['code' => 'plan-limit-reached', 'message' => 'Your plan allows 1 notification per house.']);

        self::assertSame(1, DB::table('notifications')->count());
    }

    public function testCreateRejectsANameAlreadyUsedInTheHouse(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $this->createNotification($houseId, '+61400000001', '1955-11-12 06:00:00', name: 'Marty\'s Phone');

        $this->postJson('/api/v1/notifications', $this->validPayload($houseId))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    /** @return iterable<string, array{mixed}> */
    public static function badTargets(): iterable
    {
        yield 'a landline' => ['+61290000000'];
        yield 'a New Zealand mobile' => ['+64211234567'];
        yield 'local format' => ['0400000000'];
        yield 'without the plus' => ['61400000000'];
        yield 'with spaces' => ['+61 400 000 000'];
        yield 'not a number' => ['example'];
        yield 'a number' => [61400000000];
        yield 'empty' => [''];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badTargets')]
    public function testCreateRejectsATargetThatIsNotAnAustralianMobile(mixed $target): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');

        $this->postJson('/api/v1/notifications', [...$this->validPayload($houseId), 'target' => $target])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['target']);

        self::assertSame(0, DB::table('notifications')->count());
        self::assertSame(0, DB::table('phone_numbers')->count());
    }

    public function testCreateExplainsABadTargetInPlainWords(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');

        $this->postJson('/api/v1/notifications', [...$this->validPayload($houseId), 'target' => '0400000000'])
            ->assertJsonPath('errors.target.0', 'The target must be an Australian mobile, like +61400000000.');
    }

    public function testCreateRejectsAnUnknownType(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');

        $this->postJson('/api/v1/notifications', [...$this->validPayload($houseId), 'type' => 'carrier-pigeon'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function testCreateRejectsASendTimeBetweenQuarterHours(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');

        $this->postJson('/api/v1/notifications', [...$this->validPayload($houseId), 'send_time' => '18:20:00'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.send_time.0', 'The send time must be on a quarter hour, like 18:00:00 or 18:45:00.');

        self::assertSame(0, DB::table('notifications')->count());
    }

    public function testCreateRejectsInvalidInput(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');

        $this->postJson('/api/v1/notifications', ['house_id' => $houseId])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lead_days', 'name', 'send_time', 'target', 'type']);
    }

    public function testCreateRequiresTheSecondsInASendTime(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');

        $this->postJson('/api/v1/notifications', [...$this->validPayload($houseId), 'send_time' => '18:30'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.send_time.0', 'The send time must be on a quarter hour, like 18:00:00 or 18:45:00.');

        self::assertSame(0, DB::table('notifications')->count());
    }

    public function testCreateWithoutAHouseIsNotFound(): void
    {
        $this->postJson('/api/v1/notifications', [])->assertNotFound();
    }

    public function testDeleteFreesTheNameButRecordsIt(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $notificationId = $this->createNotification($houseId, '+61400000000', '1955-11-12 06:00:00', name: 'Marty\'s Phone');

        $this->deleteJson('/api/v1/notifications/' . $notificationId)->assertNoContent();

        self::assertSame(\sprintf('%s-%s', $notificationId, 'Marty\'s Phone'), DB::table('notifications')->where('id', $notificationId)->value('name'));
        $this->postJson('/api/v1/notifications', $this->validPayload($houseId))->assertCreated()->assertJsonPath('data.name', 'Marty\'s Phone');
    }

    public function testDeleteLetsAnOverLimitUserDeleteAnInactiveNotification(): void
    {
        $this->useLimits(notificationsPerHouse: 1);
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $this->createNotification($houseId, '+61400000000', '1955-11-12 06:00:00');
        $newerId = $this->createNotification($houseId, '+61400000001', '2015-10-21 16:29:00');

        $this->deleteJson('/api/v1/notifications/' . $newerId)->assertNoContent();

        self::assertNotNull(DB::table('notifications')->where('id', $newerId)->value('deleted_at'));
        $this->getJson('/api/v1/notifications')->assertJsonCount(1, 'data');
    }

    public function testDeleteOfAnotherUsersNotificationIsNotFound(): void
    {
        $otherUserId = $this->createUser('user_222');
        $houseId = $this->createHouse('Lyon Estates', '1985-10-26 01:21:00', $otherUserId);
        $notificationId = $this->createNotification($houseId, '+61400000000', '1955-11-12 06:00:00', $otherUserId);

        $this->deleteJson('/api/v1/notifications/' . $notificationId)->assertNotFound();

        self::assertNull(DB::table('notifications')->where('id', $notificationId)->value('deleted_at'));
    }

    public function testDeleteSoftDeletesTheNotification(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $notificationId = $this->createNotification($houseId, '+61400000000', '1985-10-26 01:21:00');
        $keptId = $this->createNotification($houseId, '+61400000001', '1985-10-26 01:22:00');

        $this->deleteJson('/api/v1/notifications/' . $notificationId)->assertNoContent();

        self::assertNotNull(DB::table('notifications')->where('id', $notificationId)->value('deleted_at'));
        self::assertNull(DB::table('notifications')->where('id', $keptId)->value('deleted_at'));
    }

    public function testListLeavesOutOtherUsersNotifications(): void
    {
        $otherUserId = $this->createUser('user_222');
        $this->createNotification(
            $this->createHouse('Lyon Estates', '1985-10-26 01:21:00', $otherUserId),
            '+61400000002',
            '1955-11-12 06:00:00',
            $otherUserId,
        );

        $this->getJson('/api/v1/notifications')->assertOk()->assertExactJson(['data' => []]);
    }

    public function testListMarksNotificationsOverThePlanLimitInactive(): void
    {
        $this->useLimits(notificationsPerHouse: 1);
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $this->createNotification($houseId, '+61400000001', '2015-10-21 16:29:00');
        $this->createNotification($houseId, '+61400000000', '1955-11-12 06:00:00');

        $response = $this->getJson('/api/v1/notifications')->assertOk();

        $notifications = \collect($response->json('data'))->keyBy('target');
        self::assertTrue($notifications['+61400000000']['active']);
        self::assertFalse($notifications['+61400000001']['active']);
    }

    public function testListShowsTheSendTimeWithSeconds(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $this->createNotification($houseId, '+61400000000', '1955-11-12 06:00:00');

        // The same on every database: MySQL pads a time out to HH:MM:SS, and SQLite keeps what it was given.
        $this->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('data.0.send_time', '18:30:00');
    }

    public function testUpdateChangesTheNotification(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $notificationId = $this->createNotification($houseId, '+61400000000', '1955-11-12 06:00:00');

        $this->patchJson('/api/v1/notifications/' . $notificationId, ['lead_days' => 3, 'send_time' => '07:00:00'])
            ->assertOk()
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.lead_days', 3)
            ->assertJsonPath('data.send_time', '07:00:00')
            ->assertJsonPath('data.target', '+61400000000');

        self::assertSame(3, DB::table('notifications')->where('id', $notificationId)->value('lead_days'));
    }

    public function testUpdateChangesTheSendTime(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $notificationId = $this->createNotification($houseId, '+61400000000', '1955-11-12 06:00:00');

        $this->patchJson('/api/v1/notifications/' . $notificationId, ['send_time' => '07:30:00'])
            ->assertOk()
            ->assertJsonPath('data.send_time', '07:30:00');

        self::assertSame('07:30:00', DB::table('notifications')->where('id', $notificationId)->value('send_time'));
    }

    public function testUpdateMovesTheNotificationToANewNumberAndItWaitsThere(): void
    {
        $verifier = $this->spyOnVerifier();
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $notificationId = $this->createNotification($houseId, '+61400000000', '1955-11-12 06:00:00');
        $oldId = $this->createPhoneNumber('+61400000000');

        $response = $this->patchJson('/api/v1/notifications/' . $notificationId, ['target' => '+61400000009'])->assertOk()
            ->assertJsonPath('data.target', '+61400000009')
            ->assertJsonPath('data.verified', false);

        self::assertNotSame($oldId, $response->json('data.phone_number_id'));
        self::assertSame($response->json('data.phone_number_id'), DB::table('notifications')->where('id', $notificationId)->value('phone_number_id'));
        self::assertSame('pending', DB::table('phone_numbers')->where('number', '+61400000009')->value('status'));
        self::assertSame('verified', DB::table('phone_numbers')->where('id', $oldId)->value('status'));
        self::assertSame([], $verifier->enrolled);
    }

    public function testUpdateToAVerifiedNumberIsVerifiedAtOnce(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $notificationId = $this->createNotification($houseId, '+61400000000', '1955-11-12 06:00:00');
        $otherId = $this->createPhoneNumber('+61400000009');

        $this->patchJson('/api/v1/notifications/' . $notificationId, ['target' => '+61400000009'])->assertOk()
            ->assertJsonPath('data.verified', true)
            ->assertJsonPath('data.phone_number_id', $otherId);
    }

    public function testUpdateKeepsTheNumberWhenTheTargetIsNotSent(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $notificationId = $this->createNotification($houseId, '+61400000000', '1955-11-12 06:00:00');

        $this->patchJson('/api/v1/notifications/' . $notificationId, ['lead_days' => 2])->assertOk()
            ->assertJsonPath('data.verified', true)
            ->assertJsonPath('data.target', '+61400000000');

        self::assertSame(1, DB::table('phone_numbers')->count());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badTargets')]
    public function testUpdateRejectsATargetThatIsNotAnAustralianMobile(mixed $target): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $notificationId = $this->createNotification($houseId, '+61400000000', '1955-11-12 06:00:00');

        $this->patchJson('/api/v1/notifications/' . $notificationId, ['target' => $target])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['target']);

        self::assertSame(1, DB::table('phone_numbers')->count());
    }

    public function testUpdateIgnoresAHouseId(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $otherHouseId = $this->createHouse('Twin Pines', '2015-10-21 16:29:00');
        $notificationId = $this->createNotification($houseId, '+61400000000', '1955-11-12 06:00:00');

        $this->patchJson('/api/v1/notifications/' . $notificationId, ['house_id' => $otherHouseId, 'lead_days' => 2])
            ->assertOk();

        self::assertSame($houseId, DB::table('notifications')->where('id', $notificationId)->value('house_id'));
    }

    public function testUpdateKeepsAnOverLimitNotificationEditableAndInactive(): void
    {
        $this->useLimits(notificationsPerHouse: 1);
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $this->createNotification($houseId, '+61400000000', '1955-11-12 06:00:00');
        $notificationId = $this->createNotification($houseId, '+61400000001', '2015-10-21 16:29:00');

        $this->patchJson('/api/v1/notifications/' . $notificationId, ['type' => 'sms', 'lead_days' => 2])
            ->assertOk()
            ->assertJsonPath('data.active', false)
            ->assertJsonPath('data.lead_days', 2);
    }

    public function testUpdateOfAnotherUsersNotificationIsNotFound(): void
    {
        $otherUserId = $this->createUser('user_222');
        $houseId = $this->createHouse('Lyon Estates', '1985-10-26 01:21:00', $otherUserId);
        $notificationId = $this->createNotification($houseId, '+61400000002', '1955-11-12 06:00:00', $otherUserId);

        $this->patchJson('/api/v1/notifications/' . $notificationId, ['lead_days' => 2])->assertNotFound();

        self::assertSame(1, DB::table('notifications')->where('id', $notificationId)->value('lead_days'));
    }

    public function testUpdateRejectsASendTimeBetweenQuarterHours(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $notificationId = $this->createNotification($houseId, '+61400000000', '1955-11-12 06:00:00');

        $this->patchJson('/api/v1/notifications/' . $notificationId, ['send_time' => '07:50:00'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.send_time.0', 'The send time must be on a quarter hour, like 18:00:00 or 18:45:00.');

        self::assertSame('18:30:00', DB::table('notifications')->where('id', $notificationId)->value('send_time'));
    }

    public function testUpdateRejectsInvalidInput(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $notificationId = $this->createNotification($houseId, '+61400000000', '1955-11-12 06:00:00');

        $this->patchJson('/api/v1/notifications/' . $notificationId, ['lead_days' => 8, 'send_time' => '11:21:00', 'type' => 'carrier-pigeon'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lead_days', 'send_time', 'type']);
    }

    public function testUpdateRenamesTheNotificationButNotToANameAlreadyInTheHouse(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $notificationId = $this->createNotification($houseId, '+61400000000', '1955-11-12 06:00:00', name: 'Marty\'s Phone');
        $this->createNotification($houseId, '+61400000001', '1955-11-12 06:01:00', name: 'Doc\'s Phone');

        $this->patchJson('/api/v1/notifications/' . $notificationId, ['name' => 'Doc\'s Phone'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $this->patchJson('/api/v1/notifications/' . $notificationId, ['name' => 'Marty\'s Phone'])->assertOk();
        $this->patchJson('/api/v1/notifications/' . $notificationId, ['name' => 'Bin text'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Bin text');

        self::assertSame('Bin text', DB::table('notifications')->where('id', $notificationId)->value('name'));
    }

    private function validPayload(string $houseId): array
    {
        return [
            'house_id' => $houseId,
            'lead_days' => 1,
            'name' => 'Marty\'s Phone',
            'send_time' => '18:30:00',
            'target' => '+61400000000',
            'type' => 'sms',
        ];
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
