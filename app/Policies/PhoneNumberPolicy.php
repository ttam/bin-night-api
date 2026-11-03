<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PhoneNumber;
use App\Models\User;
use Illuminate\Auth\Access\Response;

final readonly class PhoneNumberPolicy extends AbstractPolicy
{
    public function update(User $user, PhoneNumber $phoneNumber): Response
    {
        return $phoneNumber->userId === $user->getKey()
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
