<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources;

use App\Services\Plan\ActiveEntities;
use App\Services\Plan\ActiveEntitiesLoader;
use App\Services\Plan\Interfaces\AttachActiveEntitiesInterface;
use App\Services\Plan\PlanLimits;
use Illuminate\Http\Request;

trait ActiveEntitiesRequestHelper
{
    /**
     * @param list<string> $houseIds
     * @param list<string> $binIds
     * @param list<string> $notificationIds
     */
    private function createActiveEntitiesRequest(array $houseIds = [], array $binIds = [], array $notificationIds = []): Request
    {
        $request = new Request();
        $activeEntities = new ActiveEntities(new PlanLimits(1, 2, 2, []), $houseIds, $binIds, $notificationIds);
        $request->attributes->set(
            AttachActiveEntitiesInterface::ACTIVE_ENTITIES,
            new ActiveEntitiesLoader(static fn (): ActiveEntities => $activeEntities),
        );

        return $request;
    }
}
