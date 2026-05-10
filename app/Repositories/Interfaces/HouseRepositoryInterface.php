<?php

declare(strict_types=1);

namespace App\Repositories\Interfaces;

use App\Auth\AuthenticatedUser;
use Illuminate\Support\Collection;

interface HouseRepositoryInterface
{
    /** @return \Illuminate\Support\Collection<\App\Models\House> */
    public function getHousesForUser(AuthenticatedUser $user): Collection;
}
