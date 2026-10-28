<?php

declare(strict_types=1);

namespace App\Repositories\Interfaces;

use App\Auth\AuthenticatedUser;
use App\DTO\House\CreateHouse;
use App\DTO\House\UpdateHouse;
use App\Models\House;
use Illuminate\Support\Collection;

interface HouseRepositoryInterface
{
    public function createHouse(AuthenticatedUser $authenticatedUser, CreateHouse $data): House;

    /** @return \Illuminate\Support\Collection<int, \App\Models\House> */
    public function getHousesForUser(AuthenticatedUser $user): Collection;

    public function updateHouse(House $house, UpdateHouse $data): House;
}
