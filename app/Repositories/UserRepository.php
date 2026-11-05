<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Auth\AuthenticatedUser;
use App\Auth\Exceptions\AccountDeletedException;
use App\Models\Bin;
use App\Models\House;
use App\Models\Notification;
use App\Models\PhoneNumber;
use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Services\Clock\Clock;
use DateTimeZone;
use Illuminate\Database\UniqueConstraintViolationException;
use Psr\Clock\ClockInterface;

final readonly class UserRepository implements UserRepositoryInterface
{
    public function __construct(private ClockInterface $clock = new Clock())
    {
    }

    public function deleteUserByExternalId(string $externalId): bool
    {
        $user = new User()->newQuery()->where('external_id', $externalId)->first();

        if ($user === null) {
            return false;
        }

        // One moment for all of it, so everything that went with the account can be told from what was deleted before.
        $at = $this->clock->now()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        $user->getConnection()->transaction(static function () use ($user, $at): void {
            foreach ([Bin::class, House::class, Notification::class, PhoneNumber::class] as $model) {
                new $model()->newQuery()->where('user_id', $user->getKey())->toBase()->update(['deleted_at' => $at]);
            }

            $user->newQuery()->whereKey($user->getKey())->toBase()->update(['deleted_at' => $at]);
        });

        return true;
    }

    public function getUserFromExternalId(string $externalId): User
    {
        return new User()->newQuery()
            ->where('external_id', $externalId)
            ->firstOrFail();
    }

    public function createUserFromAuthenticatedUser(AuthenticatedUser $authenticatedUser): User
    {
        // Their data is kept, but a token from a deleted account is not a way back in, and must not make a second user.
        if (new User()->newQuery()->onlyTrashed()->where('external_id', $authenticatedUser->id)->exists()) {
            throw new AccountDeletedException('This account has been deleted.');
        }

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
