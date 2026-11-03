<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Schedule;

use App\Auth\AuthenticatedUser;
use App\DTO\Schedule\UpcomingCollection;
use App\Http\Requests\Schedule\ShowScheduleRequest;
use App\Http\Resources\Concerns\HasActiveEntities;
use App\Http\Resources\UpcomingCollectionResource;
use App\Models\Bin;
use App\Models\House;
use App\Repositories\Interfaces\BinRepositoryInterface;
use App\Repositories\Interfaces\HouseRepositoryInterface;
use App\Services\Schedule\Interfaces\ScheduleServiceInterface;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Psr\Clock\ClockInterface;

final class ShowController
{
    use HasActiveEntities;

    public function __invoke(
        ShowScheduleRequest $request,
        BinRepositoryInterface $bins,
        HouseRepositoryInterface $houses,
        ScheduleServiceInterface $schedule,
        ClockInterface $clock,
    ): AnonymousResourceCollection {
        $user = AuthenticatedUser::fromRequest($request);
        $active = $this->getActiveEntities($request);
        $activeBins = $bins->getBinsForUser($user)->filter(static fn (Bin $bin): bool => $active->isBinActive($bin));

        $collections = $houses->getHousesForUser($user)
            ->flatMap(static fn (House $house): array => $schedule->getUpcomingCollections(
                $house,
                $activeBins->filter(static fn (Bin $bin): bool => $bin->houseId === $house->getKey()),
                $clock->now(),
                $request->weeks(),
            ))
            ->sortBy(static fn (UpcomingCollection $collection): string => $collection->date->format('Y-m-d'))
            ->values();

        return new AnonymousResourceCollection($collections, UpcomingCollectionResource::class);
    }
}
