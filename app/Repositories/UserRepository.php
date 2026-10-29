<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Auth\AuthenticatedUser;
use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Database\UniqueConstraintViolationException;

final readonly class UserRepository implements UserRepositoryInterface
{
    public function getUserFromExternalId(string $externalId): User
    {
        return new User()->newQuery()
            ->where('external_id', $externalId)
            ->firstOrFail();
    }

    public function createUserFromAuthenticatedUser(AuthenticatedUser $authenticatedUser): User
    {
        $profile = \array_filter([
            'email' => $authenticatedUser->email,
            'external_id' => $authenticatedUser->id,
            'name' => $authenticatedUser->name,
        ], static fn (?string $value): bool => $value !== null);

        $user = new User()->newQuery()->where('external_id', '=', $authenticatedUser->id)->first();

        if ($user === null) {
            $user = new User()->fill($profile);

            try {
                $user->save();

                return $user;
            } catch (UniqueConstraintViolationException) {
                $user = new User()->newQuery()->where('external_id', '=', $authenticatedUser->id)->firstOrFail();
            }
        }

        $user->fill($profile)->save();

        return $user;
    }
}
