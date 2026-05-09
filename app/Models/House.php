<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeZone;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

final class House extends Model
{
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
