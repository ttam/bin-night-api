<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;

final readonly class UserRepository implements UserRepositoryInterface
{
    public function getUserFromExternalId(string $externalId): User
    {
        return new User()->newQuery()
            ->where('external_id', $externalId)
            ->firstOrFail();
    }
}
