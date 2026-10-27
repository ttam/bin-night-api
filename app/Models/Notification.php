<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['lead_days', 'send_time', 'target', 'type'])]
final class Notification extends Model
{
    public House $house {
        get => $this->getRelationValue('house');
    }

    public ?int $leadDays {
        get => $this->getAttribute('lead_days');
    }

    public ?string $sendTime {
        get => $this->getAttribute('send_time');
    }

    /** Only present on notifications loaded by NotificationRepository::getDueNotifications(). */
    public string $userExternalId {
        get => $this->getAttribute('user_external_id');
    }

    public string $target {
        get => $this->getAttribute('target');
    }

    public NotificationType $type {
        get => NotificationType::from($this->getAttribute('type'));
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class);
    }

    public function house(): BelongsTo
    {
        return $this->belongsTo(House::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }
}
