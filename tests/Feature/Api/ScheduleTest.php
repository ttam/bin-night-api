<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Services\Clock\KnownClock;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Psr\Clock\ClockInterface;

/**
 * The house's bin night is Monday and it is in Los Angeles. At 13:00 on Thursday 24 October 1985 there, the first bin
 * night is Monday 28 October.
 */
final class ScheduleTest extends ApiTestCase
{
    public function testAnUnauthenticatedRequestIsRefused(): void
    {
        \config(['authentication.clerk.development_user' => null]);

        $this->getJson('/api/v1/schedule')->assertUnauthorized();
    }

    public function testAnotherUsersBinsAreNotIncluded(): void
    {
        $this->givenTheTimeIs('1985-10-24 20:00:00');
        $this->useLimits(binsPerHouse: 4);
        $mine = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $theirs = $this->createHouse('Lyon Estates', '1985-10-26 01:21:00', $this->createUser('user_222'));
        $this->createBin($mine, 'Mine', '1985-10-26 01:21:00');
        $this->createBin($theirs, 'Theirs', '1985-10-26 01:21:00', $this->createUser('user_333'));

        $this->getJson('/api/v1/schedule?weeks=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.bins.0.name', 'Mine')
            ->assertJsonPath('data.0.house_id', $mine);
    }

    public function testAnOutOfRangeNumberOfWeeksIsRefused(): void
    {
        foreach (['0', '13', 'abc'] as $weeks) {
            $this->getJson('/api/v1/schedule?weeks=' . $weeks)->assertUnprocessable()->assertJsonValidationErrors(['weeks']);
        }
    }

    public function testBinsPausedByThePlanAreLeftOut(): void
    {
        $this->givenTheTimeIs('1985-10-24 20:00:00');
        $this->useLimits(binsPerHouse: 1);
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $this->createBin($houseId, 'Oldest', '1955-11-12 06:00:00');
        $this->createBin($houseId, 'Paused', '1985-10-26 01:21:00');

        $this->getJson('/api/v1/schedule?weeks=1')
            ->assertOk()
            ->assertJsonCount(1, 'data.0.bins')
            ->assertJsonPath('data.0.bins.0.name', 'Oldest');
    }

    public function testItIsEmptyForAUserWithNoHouse(): void
    {
        $this->getJson('/api/v1/schedule')->assertOk()->assertExactJson(['data' => []]);
    }

    public function testItShowsFourWeeksOfRubbishRecyclingGardenAndGlassByDefault(): void
    {
        $this->givenTheTimeIs('1985-10-24 20:00:00');
        $this->useLimits(binsPerHouse: 4);
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $this->createBinWith($houseId, 'Rubbish', 'weekly', '1985-10-28');
        $this->createBinWith($houseId, 'Recycling', 'fortnightly', '1985-10-28');
        $this->createBinWith($houseId, 'Garden', 'fortnightly', '1985-11-04');
        $this->createBinWith($houseId, 'Glass', 'four-weekly', '1985-11-04');

        $response = $this->getJson('/api/v1/schedule')->assertOk()->assertJsonCount(4, 'data');

        self::assertSame([
            '1985-10-28' => ['Recycling', 'Rubbish'],
            '1985-11-04' => ['Garden', 'Glass', 'Rubbish'],
            '1985-11-11' => ['Recycling', 'Rubbish'],
            '1985-11-18' => ['Garden', 'Rubbish'],
        ], \collect($response->json('data'))->mapWithKeys(static fn (array $day): array => [$day['date'] => \array_column($day['bins'], 'name')])->all());
    }

    public function testItShowsTheBinsColoursAndIds(): void
    {
        $this->givenTheTimeIs('1985-10-24 20:00:00');
        $this->useLimits(binsPerHouse: 4);
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $binId = $this->createBin($houseId, 'Rubbish', '1985-10-26 01:21:00');
        DB::table('bins')->where('id', $binId)->update(['bin_color' => '475c3b', 'lid_color' => 'd21834']);

        $this->getJson('/api/v1/schedule?weeks=1')->assertOk()->assertExactJson(['data' => [[
            'bins' => [['bin_color' => '#475c3b', 'id' => $binId, 'lid_color' => '#d21834', 'name' => 'Rubbish']],
            'date' => '1985-10-28',
            'house_id' => $houseId,
        ]]]);
    }

    public function testItStopsAtTheNumberOfWeeksAsked(): void
    {
        $this->givenTheTimeIs('1985-10-24 20:00:00');
        $this->useLimits(binsPerHouse: 4);
        $houseId = $this->createHouse('Hill Valley', '1985-10-26 01:21:00');
        $this->createBin($houseId, 'Rubbish', '1985-10-26 01:21:00');

        $this->getJson('/api/v1/schedule?weeks=2')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/schedule?weeks=12')->assertOk()->assertJsonCount(12, 'data');
    }

    private function createBinWith(string $houseId, string $name, string $cycle, string $startDate): void
    {
        DB::table('bins')->where('id', $this->createBin($houseId, $name, '1985-10-26 01:21:00'))->update(['cycle' => $cycle, 'start_date' => $startDate]);
    }

    private function givenTheTimeIs(string $utc): void
    {
        $this->app->instance(ClockInterface::class, new KnownClock(new DateTimeImmutable($utc, new DateTimeZone('UTC'))));
    }
}
