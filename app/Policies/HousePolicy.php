<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\House;
use App\Models\User;
use App\Services\Plan\PlanLimits;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Str;

final readonly class HousePolicy extends AbstractPolicy
{
    public function create(User $user): Response
    {
        $limit = $this->planRepository->getLimits($user->plan)->houses;

        return ($limit === PlanLimits::UNLIMITED || $user->houses()->count() < $limit)
            ? Response::allow()
            : self::limitReached(\sprintf(
                'Your plan allows %d %s.',
                $limit,
                Str::plural('house', $limit),
            ));
    }

    public function update(User $user, House $house): Response
    {
        return $house->userId === $user->getKey()
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
