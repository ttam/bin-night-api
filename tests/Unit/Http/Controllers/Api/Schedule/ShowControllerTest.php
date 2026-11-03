<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api\Schedule;

use App\Auth\AuthenticatedUser;
use App\DTO\Schedule\UpcomingCollection;
use App\Http\Controllers\Api\Schedule\ShowController;
use App\Http\Requests\Schedule\ShowScheduleRequest;
use App\Http\Resources\UpcomingCollectionResource;
use App\Models\Bin;
use App\Models\House;
use App\Repositories\Interfaces\BinRepositoryInterface;
use App\Repositories\Interfaces\HouseRepositoryInterface;
use App\Services\Plan\ActiveEntities;
use App\Services\Plan\ActiveEntitiesLoader;
use App\Services\Plan\Interfaces\AttachActiveEntitiesInterface;
use App\Services\Plan\PlanLimits;
use App\Services\Schedule\Interfaces\ScheduleServiceInterface;
use DateTimeImmutable;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

final class ShowControllerTest extends TestCase
{
    public function testAsksForEachHousesActiveBinsOnlyAndPutsTheEarliestDateFirst(): void
    {
        $now = new DateTimeImmutable('1985-10-26 01:21:00');
        $user = new AuthenticatedUser(['sub' => 'user_111']);
        $first = new House()->setAttribute('id', 'h1');
        $second = new House()->setAttribute('id', 'h2');
        $active = $this->bin('b1', 'h1');
        $paused = $this->bin('b2', 'h1');
        $other = $this->bin('b3', 'h2');

        $bins = $this->createMock(BinRepositoryInterface::class);
        $bins->method('getBinsForUser')->with($user)->willReturn(new Collection([$active, $paused, $other]));
        $houses = $this->createMock(HouseRepositoryInterface::class);
        $houses->method('getHousesForUser')->with($user)->willReturn(new Collection([$first, $second]));

        $later = new UpcomingCollection(new DateTimeImmutable('1985-11-04'), $first, new Collection([$active]));
        $earlier = new UpcomingCollection(new DateTimeImmutable('1985-10-28'), $second, new Collection([$other]));

        $schedule = $this->createMock(ScheduleServiceInterface::class);
        $schedule->expects($this->exactly(2))->method('getUpcomingCollections')
            ->willReturnCallback(static function (House $house, Collection $given, DateTimeImmutable $at, int $weeks) use ($first, $active, $other, $now, $later, $earlier): array {
                self::assertSame($now, $at);
                self::assertSame(6, $weeks);
                self::assertSame($house === $first ? [$active] : [$other], $given->values()->all());

                return $house === $first ? [$later] : [$earlier];
            });

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($now);

        $request = ShowScheduleRequest::create('/api/v1/schedule', 'GET', ['weeks' => '6']);
        $request->setUserResolver(static fn (): AuthenticatedUser => $user);
        $activeEntities = new ActiveEntities(new PlanLimits(2, 2, 2, []), ['h1', 'h2'], ['b1', 'b3'], []);
        $request->attributes->set(AttachActiveEntitiesInterface::ACTIVE_ENTITIES, new ActiveEntitiesLoader(static fn (): ActiveEntities => $activeEntities));

        $result = new ShowController()($request, $bins, $houses, $schedule, $clock);

        self::assertSame(UpcomingCollectionResource::class, $result->collects);
        self::assertSame([$earlier, $later], $result->resource->map(static fn (UpcomingCollectionResource $resource): UpcomingCollection => $resource->resource)->all());
    }

    private function bin(string $id, string $houseId): Bin
    {
        return new Bin()->setAttribute('id', $id)->setAttribute('house_id', $houseId);
    }
}
