<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\NotificationType;
use App\Services\Plan\PlanLimits;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property \App\Services\Plan\PlanSummary $resource */
final class MeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $limits = $this->resource->limits;

        return [
            'limits' => [
                'bins_per_house' => self::limit($limits->binsPerHouse),
                'houses' => self::limit($limits->houses),
                'notification_types' => \array_map(static fn (NotificationType $type): string => $type->value, $limits->notificationTypes),
                'notifications_per_house' => self::limit($limits->notificationsPerHouse),
            ],
            'plan' => $this->resource->plan->value,
        ];
    }

    /** No limit is `null`, rather than the -1 that the code uses. */
    private static function limit(int $limit): ?int
    {
        return $limit === PlanLimits::UNLIMITED ? null : $limit;
    }
}
