<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeliveryStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One notification's delivery for one slot (a UTC half hour). There is at most one per notification and slot. */
final class NotificationLog extends EloquentModel
{
    use HasUuids;

    public DeliveryStatus $status {
        get => $this->getAttribute('status');
    }

    /** @return BelongsTo<Notification, $this> */
    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'status' => DeliveryStatus::class,
        ];
    }
}
