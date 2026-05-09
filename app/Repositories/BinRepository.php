<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Bin;
use App\Models\Notification;
use App\Repositories\Interfaces\BinRepositoryInterface;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class BinRepository implements BinRepositoryInterface
{
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
}
