<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Auth\AuthenticatedUser;
use App\DTO\Bin\CreateBin;
use App\DTO\Bin\UpdateBin;
use App\Models\Bin;
use App\Models\Notification;
use App\Models\User;
use App\Repositories\Interfaces\BinRepositoryInterface;
use App\Services\Schedule\Interfaces\ScheduleServiceInterface;
use DateTimeImmutable;
use Illuminate\Support\Collection;

final readonly class BinRepository implements BinRepositoryInterface
{
    public function __construct(private ScheduleServiceInterface $schedule)
    {
    }

    public function createBin(AuthenticatedUser $authenticatedUser, CreateBin $data): Bin
    {
        $userId = $authenticatedUser->user?->getKey() ?? User::query()->where('external_id', $authenticatedUser->id)->value('id');

        $bin = new Bin()->fill([
            'bin_color' => $data->binColor,
            'cycle' => $data->cycle->value,
            'lid_color' => $data->lidColor,
            'name' => $data->name,
            'start_date' => $data->startDate,
        ]);

        $bin->setAttribute('house_id', $data->houseId);
        $bin->setAttribute('user_id', $userId);
        $bin->save();

        return $bin;
    }

    public function deleteBin(Bin $bin): void
    {
        $bin->deleteReleasingName();
    }

    /** @inheritDoc */
    public function getBinsForNotification(Notification $notification, DateTimeImmutable $now): Collection
    {
        $house = $notification->house;
        $bins = $house->bins()->get()->each(static fn (Bin $bin): Bin => $bin->setRelation('house', $house));

        return $this->schedule->getBinsCollectedForNotification($notification, $now, $bins);
    }

    /** @inheritDoc */
    public function getBinsForUser(AuthenticatedUser $user): Collection
    {
        return new Bin()->newQuery()
            ->select('bins.*')
            ->join('users', 'users.id', '=', 'bins.user_id')
            ->where('users.external_id', '=', $user->id)
            ->get();
    }

    public function updateBin(Bin $bin, UpdateBin $data): Bin
    {
        $bin->fill($data->toDatabaseArray())->save();

        return $bin;
    }
}
