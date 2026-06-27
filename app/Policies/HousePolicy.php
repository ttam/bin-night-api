<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

final class HousePolicy
{
    public function create(User $user): Response
    {
        return $user->houses()->doesntExist()
            ? Response::allow()
            : Response::deny('You have reached the maximum limit of 1 house.');
    }
}
