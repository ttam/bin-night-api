<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\NotificationType;
use App\Enums\Plan;
use App\Repositories\Interfaces\PlanRepositoryInterface;
use App\Services\Plan\PlanLimits;

final readonly class PlanRepository implements PlanRepositoryInterface
{
    public function getLimits(Plan $plan): PlanLimits
    {
        return match ($plan) {
            Plan::FREE => new PlanLimits(
                houses: 1,
                binsPerHouse: 2,
                notificationsPerHouse: 2,
                notificationTypes: [NotificationType::SMS],
            ),

            Plan::PLUS => new PlanLimits(
                houses: 1,
                binsPerHouse: 5,
                notificationsPerHouse: 5,
                notificationTypes: [NotificationType::SMS],
            ),

            Plan::UNLIMITED => new PlanLimits(
                houses: 1,
                binsPerHouse: -1,
                notificationsPerHouse: 20,
                notificationTypes: [NotificationType::SMS],
            ),
        };
    }
}
