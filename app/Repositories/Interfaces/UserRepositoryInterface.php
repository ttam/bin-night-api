<?php

declare(strict_types=1);

namespace App\Repositories\Interfaces;

use App\Auth\AuthenticatedUser;
use App\Models\User;

interface UserRepositoryInterface
{
    /** @throws \App\Auth\Exceptions\AccountDeletedException for a user that has been deleted */
    public function createUserFromAuthenticatedUser(AuthenticatedUser $authenticatedUser): User;

    /**
     * Delete the user's account, keeping the data. The user, their houses, bins, notifications and phone numbers are soft
     * deleted together (all with the same `deleted_at`, so they can be told from anything deleted earlier), and their
     * delivery logs stay as they are. Nothing of theirs is sent or listed after that. Returns whether there was such a
     * user to delete.
     */
    public function deleteUserByExternalId(string $externalId): bool;

    public function getUserFromExternalId(string $externalId): User;
}
