<?php

declare(strict_types=1);

namespace App\Models;

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
