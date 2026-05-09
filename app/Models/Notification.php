<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Notification extends Model
{
    public House $house {
        get => $this->getRelationValue('house');
    }

    public ?int $leadDays {
        get => $this->getAttribute('lead_days');
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
