<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\ReleasesNameOnDelete;
use DateTimeZone;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['bin_night', 'name', 'timezone'])]
final class House extends Model
{
    use ReleasesNameOnDelete;
    use SoftDeletes;

    public string $binNight {
        get => $this->getAttribute('bin_night');
    }

    public string $name {
        get => $this->getAttribute('name');
    }

    public DateTimeZone $timezone {
        get {
            /** @var string $timezone */
            $timezone = $this->getAttribute('timezone');

            return new DateTimeZone($timezone);
        }
    }

    /** @return HasMany<Bin, $this> */
    public function bins(): HasMany
    {
        return $this->hasMany(Bin::class);
    }

    /** @return HasMany<Notification, $this> */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }
}
