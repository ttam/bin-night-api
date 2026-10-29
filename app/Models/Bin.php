<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Cycle;
use App\Models\Concerns\ReleasesNameOnDelete;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['bin_color', 'cycle', 'lid_color', 'name', 'start_date'])]
final class Bin extends Model
{
    use ReleasesNameOnDelete;
    use SoftDeletes;

    public string $binColor {
        get => $this->getAttribute('bin_color');
    }

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

    public string $houseId {
        get => $this->getAttribute('house_id');
    }

    public string $lidColor {
        get => $this->getAttribute('lid_color');
    }

    public string $name {
        get => $this->getAttribute('name');
    }

    public DateTimeImmutable $startDate {
        get => $this->getAttribute('start_date');
    }

    /** @return BelongsTo<House, $this> */
    public function house(): BelongsTo
    {
        return $this->belongsTo(House::class);
    }

    protected function casts(): array
    {
        return [
            'cycle' => Cycle::class,
            'start_date' => 'immutable_date:Y-m-d',
        ];
    }
}
