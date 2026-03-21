<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

final class House extends Model
{
    public function bins(): HasMany
    {
        return $this->hasMany(Bin::class);
    }

    public function notifications(): HasManyThrough
    {
        return $this->hasManyThrough(Notification::class, Bin::class);
    }
}
