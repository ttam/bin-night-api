<?php

declare(strict_types=1);

namespace App\Repositories\Interfaces;

use App\Auth\AuthenticatedUser;
use App\Models\User;

interface UserRepositoryInterface
{
    public function createUserFromAuthenticatedUser(AuthenticatedUser $authenticatedUser): User;

    public function getUserFromExternalId(string $externalId): User;
}
