<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\Cycle;
use App\Services\Clock\KnownClock;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Psr\Clock\ClockInterface;

final class BinsTest extends ApiTestCase
{
    public function testCreateCountsTheNewBinAsActive(): void
    {
        $this->useLimits(binsPerHouse: 1);
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');

        $this->postJson('/api/v1/bins', $this->validPayload($houseId))
            ->assertCreated()
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.name', 'Rubbish');

        self::assertSame(1, DB::table('bins')->count());
    }

    public function testCreateInAnotherUsersHouseIsNotFound(): void
    {
        $houseId = $this->createHouse('Lyon Estates', '1985-10-26 01:21:00', $this->createUser('user_222'));

        $this->postJson('/api/v1/bins', $this->validPayload($houseId))->assertNotFound();

        self::assertSame(0, DB::table('bins')->count());
    }

    public function testCreateKeepsAGivenStartDateAndUsesItForTheNextBinNight(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $this->givenTheTimeIs('1985-10-24 20:00:00');

        // A fortnightly bin that starts on Monday 4 November does not go out on Monday 28 October.
        $this->postJson('/api/v1/bins', [...$this->validPayload($houseId), 'cycle' => 'fortnightly', 'start_date' => '1985-11-04'])
            ->assertCreated()
            ->assertJsonPath('data.start_date', '1985-11-04')
            ->assertJsonPath('data.next_bin_night', '1985-11-04T00:00:00-08:00');
    }

    public function testCreateOverThePlanLimitIsRejected(): void
    {
        $this->useLimits(binsPerHouse: 1);
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $this->createBin($houseId, 'Recycling', '1955-11-12 06:00:00');

        $this->postJson('/api/v1/bins', $this->validPayload($houseId))
            ->assertForbidden()
            ->assertExactJson(['code' => 'plan-limit-reached', 'message' => 'Your plan allows 1 bin per house.']);

        self::assertSame(1, DB::table('bins')->count());
    }

    public function testCreateRejectsAnInvalidStartDate(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');

        foreach (['1985-02-30', '06/11/1985', 'next monday'] as $startDate) {
            $this->postJson('/api/v1/bins', [...$this->validPayload($houseId), 'start_date' => $startDate])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['start_date']);
        }

        self::assertSame(0, DB::table('bins')->count());
    }

    public function testCreateRejectsInvalidInput(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');

        $this->postJson('/api/v1/bins', ['house_id' => $houseId])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['bin_color', 'cycle', 'lid_color', 'name']);
    }

    /** @return iterable<string, array{mixed}> */
    public static function badColours(): iterable
    {
        yield 'three digits' => ['#abc'];
        yield 'four digits' => ['#abcd'];
        yield 'eight digits, which the column cannot hold' => ['#aabbccdd'];
        yield 'without the hash' => ['aabbcc'];
        yield 'not hex' => ['#gggggg'];
        yield 'a name' => ['red'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badColours')]
    public function testCreateRejectsAColourThatIsNotSixHexDigits(mixed $colour): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');

        foreach (['bin_color' => 'The bin colour must be a colour like #0061a4.', 'lid_color' => 'The lid colour must be a colour like #0061a4.'] as $field => $message) {
            $this->postJson('/api/v1/bins', [...$this->validPayload($houseId), $field => $colour])
                ->assertUnprocessable()
                ->assertJsonPath("errors.{$field}.0", $message);
        }

        self::assertSame(0, DB::table('bins')->count());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badColours')]
    public function testUpdateRejectsAColourThatIsNotSixHexDigits(mixed $colour): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $binId = $this->createBin($houseId, 'Rubbish', '1985-10-26 01:21:00');

        $this->patchJson('/api/v1/bins/' . $binId, ['bin_color' => $colour])->assertUnprocessable()->assertJsonValidationErrors(['bin_color']);
        $this->patchJson('/api/v1/bins/' . $binId, ['lid_color' => $colour])->assertUnprocessable()->assertJsonValidationErrors(['lid_color']);

        self::assertSame('000000', DB::table('bins')->where('id', $binId)->value('bin_color'));
    }

    public function testAColourInEitherCaseIsKeptAsSixDigits(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');

        $this->postJson('/api/v1/bins', [...$this->validPayload($houseId), 'bin_color' => '#AABBCC', 'lid_color' => '#0061a4'])
            ->assertCreated()
            ->assertJsonPath('data.bin_color', '#AABBCC')
            ->assertJsonPath('data.lid_color', '#0061a4');

        self::assertSame('AABBCC', DB::table('bins')->value('bin_color'));
    }

    public function testCreateRequiresAStartDate(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $payload = $this->validPayload($houseId);
        unset($payload['start_date']);

        $this->postJson('/api/v1/bins', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_date']);

        self::assertSame(0, DB::table('bins')->count());
    }

    public function testCreateWithoutAHouseIsNotFound(): void
    {
        $this->postJson('/api/v1/bins', [])->assertNotFound();
    }

    public function testDeleteFreesTheNameButRecordsIt(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $binId = $this->createBin($houseId, 'Rubbish', '1985-10-26 01:21:00');

        $this->deleteJson('/api/v1/bins/' . $binId)->assertNoContent();

        self::assertSame($binId . '-Rubbish', DB::table('bins')->where('id', $binId)->value('name'));
        $this->postJson('/api/v1/bins', $this->validPayload($houseId))->assertCreated()->assertJsonPath('data.name', 'Rubbish');
    }

    public function testDeleteLetsAnOverLimitUserDeleteAnInactiveBin(): void
    {
        $this->useLimits(binsPerHouse: 1);
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $this->createBin($houseId, 'Older', '1955-11-12 06:00:00');
        $newerId = $this->createBin($houseId, 'Newer', '2015-10-21 16:29:00');

        $this->deleteJson('/api/v1/bins/' . $newerId)->assertNoContent();

        self::assertNotNull(DB::table('bins')->where('id', $newerId)->value('deleted_at'));
        $this->getJson('/api/v1/bins')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Older');
    }

    public function testDeleteOfAnotherUsersBinIsNotFound(): void
    {
        $otherUserId = $this->createUser('user_222');
        $houseId = $this->createHouse('Lyon Estates', '1985-10-26 01:21:00', $otherUserId);
        $binId = $this->createBin($houseId, 'Rubbish', '1955-11-12 06:00:00', $otherUserId);

        $this->deleteJson('/api/v1/bins/' . $binId)->assertNotFound();

        self::assertNull(DB::table('bins')->where('id', $binId)->value('deleted_at'));
    }

    public function testDeleteSoftDeletesTheBin(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $binId = $this->createBin($houseId, 'Rubbish', '1985-10-26 01:21:00');
        $keptId = $this->createBin($houseId, 'Recycling', '1985-10-26 01:22:00');

        $this->deleteJson('/api/v1/bins/' . $binId)->assertNoContent();

        self::assertNotNull(DB::table('bins')->where('id', $binId)->value('deleted_at'));
        self::assertNull(DB::table('bins')->where('id', $keptId)->value('deleted_at'));
    }

    public function testListMarksBinsOverThePlanLimitInactive(): void
    {
        $this->useLimits(binsPerHouse: 1);
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $this->createBin($houseId, 'Newer', '2015-10-21 16:29:00');
        $this->createBin($houseId, 'Older', '1955-11-12 06:00:00');

        $response = $this->getJson('/api/v1/bins')->assertOk();

        $bins = \collect($response->json('data'))->keyBy('name');
        self::assertTrue($bins['Older']['active']);
        self::assertNotNull($bins['Older']['next_bin_night']);
        self::assertFalse($bins['Newer']['active']);
        self::assertNull($bins['Newer']['next_bin_night']);
    }

    public function testNothingGoesOutBeforeTheStartDate(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $this->givenTheTimeIs('1985-10-24 20:00:00');

        // The house's bin night is Monday. A weekly bin that starts on Thursday 5 December goes out from Monday the 9th.
        $this->postJson('/api/v1/bins', [...$this->validPayload($houseId), 'start_date' => '1985-12-05'])
            ->assertCreated()
            ->assertJsonPath('data.start_date', '1985-12-05')
            ->assertJsonPath('data.next_bin_night', '1985-12-09T00:00:00-08:00');
    }

    public function testRequiresAuthentication(): void
    {
        \config(['authentication.clerk.development_user' => null]);

        $this->getJson('/api/v1/bins')->assertUnauthorized();
    }

    public function testUpdateCanChangeTheCycleWithoutSayingWhichWeek(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $binId = $this->createBin($houseId, 'Rubbish', '1955-11-12 06:00:00');
        $this->givenTheTimeIs('1985-10-24 20:00:00');

        foreach (Cycle::cases() as $cycle) {
            $this->patchJson('/api/v1/bins/' . $binId, ['cycle' => $cycle->value])
                ->assertOk()
                ->assertJsonPath('data.cycle', $cycle->value)
                ->assertJsonPath('data.start_date', '1985-10-26');
        }
    }

    public function testUpdateChangesTheBin(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $binId = $this->createBin($houseId, 'Rubbish', '1955-11-12 06:00:00');

        $this->patchJson('/api/v1/bins/' . $binId, ['name' => 'Garden'])
            ->assertOk()
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.name', 'Garden');

        self::assertSame('Garden', DB::table('bins')->where('id', $binId)->value('name'));
    }

    public function testUpdateChangesTheStartDateAndSoTheWeeks(): void
    {
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $binId = $this->createBin($houseId, 'Rubbish', '1955-11-12 06:00:00');
        DB::table('bins')->where('id', $binId)->update(['cycle' => 'fortnightly', 'start_date' => '1985-10-28']);
        $this->givenTheTimeIs('1985-10-29 20:00:00');

        // Moving the start date a week moves the fortnightly bin to the other weeks.
        $this->patchJson('/api/v1/bins/' . $binId, ['start_date' => '1985-11-04'])
            ->assertOk()
            ->assertJsonPath('data.start_date', '1985-11-04')
            ->assertJsonPath('data.next_bin_night', '1985-11-04T00:00:00-08:00');

        $this->patchJson('/api/v1/bins/' . $binId, ['start_date' => '1985-10-28'])
            ->assertOk()
            ->assertJsonPath('data.next_bin_night', '1985-11-11T00:00:00-08:00');
    }

    public function testUpdateKeepsAnOverLimitBinEditableAndInactive(): void
    {
        $this->useLimits(binsPerHouse: 1);
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $this->createBin($houseId, 'Older', '1955-11-12 06:00:00');
        $binId = $this->createBin($houseId, 'Newer', '2015-10-21 16:29:00');

        $this->patchJson('/api/v1/bins/' . $binId, ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.active', false)
            ->assertJsonPath('data.name', 'Renamed')
            ->assertJsonPath('data.next_bin_night', null);
    }

    public function testUpdateOfAnotherUsersBinIsNotFound(): void
    {
        $otherUserId = $this->createUser('user_222');
        $houseId = $this->createHouse('Lyon Estates', '1985-10-26 01:21:00', $otherUserId);
        $binId = $this->createBin($houseId, 'Rubbish', '1955-11-12 06:00:00', $otherUserId);

        $this->patchJson('/api/v1/bins/' . $binId, ['name' => 'Garden'])->assertNotFound();

        self::assertSame('Rubbish', DB::table('bins')->where('id', $binId)->value('name'));
    }

    private function givenTheTimeIs(string $utc): void
    {
        $this->app->instance(ClockInterface::class, new KnownClock(new DateTimeImmutable($utc, new DateTimeZone('UTC'))));
    }

    private function validPayload(string $houseId): array
    {
        return [
            'bin_color' => '#000000',
            'cycle' => 'weekly',
            'house_id' => $houseId,
            'lid_color' => '#ffffff',
            'name' => 'Rubbish',
            'start_date' => '1985-10-21',
        ];
    }
}
