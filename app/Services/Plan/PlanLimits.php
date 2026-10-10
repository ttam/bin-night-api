<?php

declare(strict_types=1);

namespace App\Services\Plan;

use App\Enums\NotificationType;
use Illuminate\Support\Collection;

final readonly class PlanLimits
{
    public const int UNLIMITED = -1;

    /** @param list<\App\Enums\NotificationType> $notificationTypes */
    public function __construct(
        public int $houses,
        public int $binsPerHouse,
        public int $notificationsPerHouse,
        public array $notificationTypes,
    ) {
    }

    /**
     * @template T
     *
     * @param \Illuminate\Support\Collection<int, T> $items oldest first
     *
     * @return \Illuminate\Support\Collection<int, T>
     */
    public static function within(Collection $items, int $limit): Collection
    {
        return $limit === self::UNLIMITED ? $items : $items->take($limit);
    }

    public function allowsNotificationType(NotificationType $type): bool
    {
        return \in_array($type, $this->notificationTypes, true);
    }
}
