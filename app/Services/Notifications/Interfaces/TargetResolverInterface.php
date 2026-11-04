<?php

declare(strict_types=1);

namespace App\Services\Notifications\Interfaces;

use App\Auth\AuthenticatedUser;
use App\Enums\NotificationType;
use App\Models\PhoneNumber;

/** Turns what a user typed as a notification's target into the destination that has to be verified before it is used. */
interface TargetResolverInterface
{
    /** The user's destination for the target, made (unverified, and without sending anything) if they have none yet. */
    public function resolve(AuthenticatedUser $user, NotificationType $type, string $target): PhoneNumber;
}
