<?php

declare(strict_types=1);

namespace App\Services\Plan;

use App\Models\Bin;
use App\Models\House;
use App\Models\Notification;

final readonly class ActiveEntities
{
    /**
     * @param list<string> $activeHouseIds
     * @param list<string> $activeBinIds
     * @param list<string> $activeNotificationIds
     */
    public function __construct(
        public PlanLimits $limits,
        private array $activeHouseIds,
        private array $activeBinIds,
        private array $activeNotificationIds,
    ) {
    }

    public function isBinActive(Bin $bin): bool
    {
        return \in_array($bin->getKey(), $this->activeBinIds, true);
    }

    public function isHouseActive(House $house): bool
    {
        return \in_array($house->getKey(), $this->activeHouseIds, true);
    }

    public function isNotificationActive(Notification $notification): bool
    {
        return \in_array($notification->getKey(), $this->activeNotificationIds, true);
    }
}
