<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Auth\AuthenticatedUser;
use App\DTO\House\CreateHouse;
use App\DTO\House\UpdateHouse;
use App\Models\House;
use App\Models\User;
use App\Repositories\Interfaces\HouseRepositoryInterface;
use Illuminate\Support\Collection;

final readonly class HouseRepository implements HouseRepositoryInterface
{
    public function createHouse(AuthenticatedUser $authenticatedUser, CreateHouse $data): House
    {
        $userId = User::query()->where('external_id', $authenticatedUser->id)->value('id');

        $house = new House()->fill([
            'bin_night' => $data->binNight->value,
            'name' => $data->name,
            'timezone' => $data->timezone->getName(),
        ])->setAttribute('user_id', $userId);

        $house->save();

        return $house;
    }

    /** @inheritDoc */
    public function getHousesForUser(AuthenticatedUser $user): Collection
    {
        return new House()->newQuery()
            ->select('houses.*')
            ->join('users', 'users.id', '=', 'houses.user_id')
            ->where('users.external_id', '=', $user->id)
            ->get();
    }

    public function updateHouse(House $house, UpdateHouse $data): House
    {
        $house->fill($data->toDatabaseArray())->save();

        return $house;
    }
}
