<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeZone;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable(['bin_night', 'name', 'timezone'])]
final class House extends Model
{
    public string $binNight {
        get => $this->getAttribute('bin_night');
    }

    public string $name {
        get => $this->getAttribute('name');
    }

    public DateTimeZone $timezone {
        get => new DateTimeZone($this->getAttribute('timezone'));
    }

    public function bins(): HasMany
    {
        return $this->hasMany(Bin::class);
    }

    public function notifications(): HasManyThrough
    {
        return $this->hasManyThrough(Notification::class, Bin::class);
    }
}
