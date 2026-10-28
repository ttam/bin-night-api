<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Auth\AuthenticatedUser;
use App\Models\Bin;
use App\Models\House;
use App\Models\Notification;
use App\Models\User;
use App\Repositories\Interfaces\ActiveEntityRepositoryInterface;
use App\Repositories\Interfaces\PlanRepositoryInterface;
use App\Services\Plan\ActiveEntities;
use App\Services\Plan\PlanLimits;
use Illuminate\Support\Collection;

final readonly class ActiveEntityRepository implements ActiveEntityRepositoryInterface
{
    public function __construct(private PlanRepositoryInterface $planRepository)
    {
    }

    public function getActiveEntitiesForUser(AuthenticatedUser $user): ActiveEntities
    {
        $owner = User::query()->where('external_id', '=', $user->id)->firstOrFail();
        /** @var string $userId */
        $userId = $owner->getKey();
        $planLimits = $this->planRepository->getLimits($owner->plan);

        $houses = $this->getHouses($userId, $planLimits);
        $bins = $this->getBins($userId, $houses, $planLimits);
        $notifications = $this->getNotifications($userId, $houses, $planLimits);

        return new ActiveEntities(
            $planLimits,
            $houses,
            $bins,
            $notifications,
        );
    }

    /** @return list<string> */
    private function getHouses(string $userId, PlanLimits $planLimits): array
    {
        $houseQuery = new House()->newQuery()
            ->where('user_id', '=', $userId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id']);

        /** @var list<string> */
        return PlanLimits::within($houseQuery, $planLimits->houses)->pluck('id')->all();
    }

    /**
     * @param list<string> $houses
     *
     * @return list<string>
     */
    private function getBins(string $userId, array $houses, PlanLimits $planLimits): array
    {
        $binQuery = new Bin()->newQuery()
            ->where('user_id', '=', $userId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'house_id']);

        return $this->activeInActiveHouses($binQuery, $houses, $planLimits->binsPerHouse);
    }

    /**
     * @param list<string> $houses
     *
     * @return list<string>
     */
    private function getNotifications(string $userId, array $houses, PlanLimits $planLimits): array
    {
        $notificationQuery = new Notification()->newQuery()
            ->where('user_id', '=', $userId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'house_id', 'type'])
            ->filter(static fn (Notification $notification): bool => $planLimits->allowsNotificationType($notification->type));

        return $this->activeInActiveHouses($notificationQuery, $houses, $planLimits->notificationsPerHouse);
    }

    /**
     * @template TItem of \App\Models\Bin|\App\Models\Notification
     *
     * @param \Illuminate\Support\Collection<int, TItem> $items oldest first
     * @param list<string> $activeHouseIds
     *
     * @return list<string>
     */
    private function activeInActiveHouses(Collection $items, array $activeHouseIds, int $limitPerHouse): array
    {
        /** @var list<string> */
        return $items
            ->filter(static fn ($item): bool => \in_array($item->houseId, $activeHouseIds, true))
            ->groupBy(static fn ($item): string => $item->houseId)
            ->flatMap(static fn (Collection $group): Collection => PlanLimits::within($group, $limitPerHouse))
            ->pluck('id')
            ->all();
    }
}
