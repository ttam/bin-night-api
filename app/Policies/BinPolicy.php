<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Bin;
use App\Models\House;
use App\Models\User;
use App\Services\Plan\PlanLimits;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Str;

final readonly class BinPolicy extends AbstractPolicy
{
    /**
     * $house is null if an invalid one is passed in the request.
     * not found and not allowed are treated the same, but we're
     * not giving anything away to the user.
     */
    public function create(User $user, ?House $house): Response
    {
        if ($house?->userId !== $user->getKey()) {
            return Response::denyAsNotFound();
        }

        $limit = $this->planRepository->getLimits($user->plan)->binsPerHouse;

        return ($limit === PlanLimits::UNLIMITED || $house->bins()->count() < $limit)
            ? Response::allow()
            : self::limitReached(\sprintf(
                'Your plan allows %d %s per house.',
                $limit,
                Str::plural('bin', $limit),
            ));
    }

    public function update(User $user, Bin $bin): Response
    {
        return $bin->userId === $user->getKey()
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
