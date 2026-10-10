<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property \App\Models\Notification $resource */
final class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'lead_days' => $this->resource->leadDays,
            'send_time' => $this->resource->sendTime,
            'target' => $this->resource->target,
            'type' => $this->resource->type->value,
        ];
    }
}
