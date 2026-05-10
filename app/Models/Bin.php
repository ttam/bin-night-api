<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Cycle;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Bin extends Model
{
    public ?DateTimeInterface $computedNextBinNight {
        get => $this->getAttribute('computed_next_bin_night');
    }

    public Cycle $cycle {
        get => $this->getAttribute('cycle');

        set(Cycle $cycle) {
            $this->setAttribute('cycle', $cycle->value);
        }
    }

    public House $house {
        get => $this->getRelationValue('house');
    }

    public string $name {
        get => $this->getAttribute('name');
    }

    public ?int $weekOfMonth {
        get => $this->getAttribute('week_of_month');
    }

    public DateTimeInterface $startDate {
        get => $this->getAttribute('start_date');
    }

    public function house(): BelongsTo
    {
        return $this->belongsTo(House::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    protected function casts(): array
    {
        return [
            'cycle' => Cycle::class,
            'start_date' => 'datetime',
        ];
    }
}
