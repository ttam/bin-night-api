<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Auth\AuthenticatedUser;
use App\Models\House;
use App\Repositories\Interfaces\HouseRepositoryInterface;
use Illuminate\Support\Collection;

final readonly class HouseRepository implements HouseRepositoryInterface
{
    /** @inheritDoc */
    public function getHousesForUser(AuthenticatedUser $user): Collection
    {
        return new House()->newQuery()
            ->select('houses.*')
            ->join('users', 'users.id', '=', 'houses.user_id')
            ->where('users.external_id', '=', $user->id)
            ->get();
    }
}
