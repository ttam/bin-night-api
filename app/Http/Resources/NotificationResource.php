<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Concerns\HasActiveEntities;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property \App\Models\Notification $resource */
final class NotificationResource extends JsonResource
{
    use HasActiveEntities;

    public function toArray(Request $request): array
    {
        return [
            'active' => $this->getActiveEntities($request)->isNotificationActive($this->resource),
            'id' => $this->resource->getKey(),
            'lead_days' => $this->resource->leadDays,
            'send_time' => $this->resource->sendTime,
            'target' => $this->resource->target,
            'type' => $this->resource->type->value,
        ];
    }
}
