<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Bin;
use App\Models\House;
use App\Models\User;
use Illuminate\Auth\Access\Response;

final class BinPolicy
{
    /**
     * $house is null if an invalid one is passed in the request.
     * not found and not allowed are treated the same, but we're
     * not giving anything away to the user.
     */
    public function create(User $user, ?House $house): Response
    {
        return $house?->userId === $user->getKey()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $user, Bin $bin): Response
    {
        return $bin->userId === $user->getKey()
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
