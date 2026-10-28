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
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final readonly class BinRepository implements BinRepositoryInterface
{
    public function createBin(AuthenticatedUser $authenticatedUser, CreateBin $data): Bin
    {
        $userId = User::query()->where('external_id', $authenticatedUser->id)->value('id');

        $bin = new Bin()->fill([
            'bin_color' => $data->binColor,
            'cycle' => $data->cycle->value,
            'lid_color' => $data->lidColor,
            'name' => $data->name,
        ]);

        $bin->setAttribute('house_id', $data->houseId);
        $bin->setAttribute('start_date', new DateTimeImmutable('today', new DateTimeZone('UTC')));
        $bin->setAttribute('user_id', $userId);
        $bin->save();

        return $bin;
    }

    /** @inheritDoc */
    public function getBinsForNotification(Notification $notification, DateTimeImmutable $now): Collection
    {
        $house = $notification->house;

        $nowLocal = DateTimeImmutable::createFromInterface($now)->setTimezone($house->timezone);
        $pickupDate = $nowLocal->modify(\sprintf('+%d days', $notification->leadDays));

        $dateStr = $pickupDate->format('Y-m-d');
        $dayOfWeek = \strtolower($pickupDate->format('l'));
        $weekOfMonth = \intval(\ceil(\intval($pickupDate->format('j')) / 7));

        return new Bin()->newQuery()
            ->where('house_id', '=', $house->getKey())

            ->where(
                static fn (Builder $query): Builder => $query
                // Weekly
                ->orWhere(static fn (Builder $query): Builder => $query
                    ->where('cycle', '=', 'weekly')
                    ->where('day_of_week', '=', $dayOfWeek))

                // Fortnightly
                ->orWhere(static fn (Builder $query): Builder => $query
                    ->where('cycle', '=', 'fortnightly')
                    ->where('day_of_week', '=', $dayOfWeek)
                    ->whereRaw('CAST((JULIANDAY(?) - JULIANDAY(start_date)) / 7 AS INTEGER) % 2 = 0', [$dateStr]))

                // Monthly
                ->orWhere(static fn (Builder $query): Builder => $query
                    ->where('cycle', '=', 'monthly')
                    ->where('day_of_week', $dayOfWeek)
                    ->where('week_of_month', $weekOfMonth))
            )->get();
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
