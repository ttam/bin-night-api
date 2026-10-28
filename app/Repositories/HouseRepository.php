<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Auth\AuthenticatedUser;
use App\DTO\House\CreateHouse;
use App\DTO\House\UpdateHouse;
use App\Models\Bin;
use App\Models\House;
use App\Models\Notification;
use App\Models\User;
use App\Repositories\Interfaces\HouseRepositoryInterface;
use Illuminate\Support\Collection;

final readonly class HouseRepository implements HouseRepositoryInterface
{
    public function createHouse(AuthenticatedUser $authenticatedUser, CreateHouse $data): House
    {
        $userId = User::query()->where('external_id', $authenticatedUser->id)->value('id');

        /** @var House $house */
        $house = new House()->fill([
            'bin_night' => $data->binNight->value,
            'name' => $data->name,
            'timezone' => $data->timezone->getName(),
        ])->setAttribute('user_id', $userId);

        $house->save();

        return $house;
    }

    public function deleteHouse(House $house): void
    {
        $house->getConnection()->transaction(static function () use ($house): void {
            $house->bins()->get()->each(static fn (Bin $bin) => $bin->deleteReleasingName());
            $house->notifications()->get()->each(static fn (Notification $notification) => $notification->deleteReleasingName());
            $house->deleteReleasingName();
        });
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
