<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

abstract class Model extends EloquentModel
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    use HasUuids;

    public string $userId {
        get => $this->getAttribute('user_id');
    }

    public User $user {
        get => $this->getRelationValue('user');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
