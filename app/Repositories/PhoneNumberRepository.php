<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Auth\AuthenticatedUser;
use App\Enums\PhoneNumberStatus;
use App\Models\PhoneNumber;
use App\Models\User;
use App\Repositories\Interfaces\PhoneNumberRepositoryInterface;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;

final readonly class PhoneNumberRepository implements PhoneNumberRepositoryInterface
{
    public function findOrCreate(AuthenticatedUser $user, string $number): PhoneNumber
    {
        $userId = $user->user?->getKey() ?? User::query()->where('external_id', $user->id)->value('id');
        $mine = static fn (): Builder => new PhoneNumber()->newQuery()->where('user_id', $userId)->where('number', $number);

        $phoneNumber = $mine()->first();

        if ($phoneNumber !== null) {
            return $phoneNumber;
        }

        $phoneNumber = new PhoneNumber();
        $phoneNumber->setAttribute('user_id', $userId);
        $phoneNumber->setAttribute('number', $number);
        $phoneNumber->setAttribute('status', PhoneNumberStatus::PENDING->value);

        try {
            $phoneNumber->save();
        } catch (UniqueConstraintViolationException) {
            // A second request for the same number won the race.
            return $mine()->firstOrFail();
        }

        return $phoneNumber;
    }

    public function markVerified(PhoneNumber $phoneNumber, DateTimeImmutable $at): PhoneNumber
    {
        $phoneNumber->setAttribute('status', PhoneNumberStatus::VERIFIED->value);
        $phoneNumber->setAttribute('verified_at', $at);
        $phoneNumber->save();

        return $phoneNumber;
    }

    public function pruneUnverified(DateTimeImmutable $before): int
    {
        return new PhoneNumber()->newQuery()->toBase()
            ->where('status', '=', PhoneNumberStatus::PENDING->value)
            ->where('created_at', '<', $before)
            ->delete();
    }
}
