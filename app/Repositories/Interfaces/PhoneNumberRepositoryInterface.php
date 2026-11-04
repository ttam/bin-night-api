<?php

declare(strict_types=1);

namespace App\Repositories\Interfaces;

use App\Auth\AuthenticatedUser;
use App\Models\PhoneNumber;
use DateTimeImmutable;

interface PhoneNumberRepositoryInterface
{
    /** The user's number, or a new pending one. Asking again for a number they have returns the same row. */
    public function findOrCreate(AuthenticatedUser $user, string $number): PhoneNumber;

    public function markVerified(PhoneNumber $phoneNumber, DateTimeImmutable $at): PhoneNumber;

    /** Delete numbers that were created before `$before` and never verified. Returns how many. */
    public function pruneUnverified(DateTimeImmutable $before): int;
}
