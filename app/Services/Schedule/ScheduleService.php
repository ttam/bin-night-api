<?php

declare(strict_types=1);

namespace App\Services\Schedule;

use App\Enums\Cycle;
use App\Models\Bin;
use App\Models\Notification;
use App\Services\Schedule\Interfaces\ScheduleServiceInterface;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Collection;

/**
 * A bin goes out on the house's bin night, from its `start_date` onwards. Its first collection is the first bin night on
 * or after that date. A weekly bin then goes out every week, a fortnightly bin every 14 days from the first
 * collection, and a monthly bin in the same week of the month as the first collection (the 2nd Wednesday, say). A
 * first collection in the fifth week gives a bin that only goes out in the months that have one.
 */
final class ScheduleService implements ScheduleServiceInterface
{
    public function computeNextBinNight(Bin $bin, DateTimeInterface $now): ?DateTimeInterface
    {
        $date = DateTimeImmutable::createFromInterface($now)
            ->setTimezone($bin->house->timezone)
            ->setTime(12, 0);

        // Nothing goes out before the start date, however far off that is.
        $start = $this->startDate($bin);
        $date = \max($date, $start);

        // Search up to a year ahead. More than enough for weekly/fortnightly/monthly.
        for ($i = 0; $i <= 370; $i++) {
            if ($this->isCollectedOn($bin, $date)) {
                return $date->setTime(0, 0);
            }

            $date = $date->modify('+1 day');
        }

        return null;
    }

    /** @inheritDoc */
    public function getBinsCollectedForNotification(Notification $notification, DateTimeImmutable $slot, Collection $bins): Collection
    {
        $pickupDate = $slot
            ->setTimezone($notification->house->timezone)
            ->setTime(12, 0)
            ->modify(\sprintf('+%d days', $notification->leadDays));

        return $bins->filter(fn (Bin $bin): bool => $this->isCollectedOn($bin, $pickupDate))->values();
    }

    /** Whether the bin goes out on the day $date falls on, in the house's timezone. */
    private function isCollectedOn(Bin $bin, DateTimeImmutable $date): bool
    {
        if ($bin->house->binNight !== \strtolower($date->format('l'))) {
            return false;
        }

        $first = $this->firstCollection($bin);

        if ($date->format('Y-m-d') < $first->format('Y-m-d')) {
            return false;
        }

        return match ($bin->cycle) {
            Cycle::FORTNIGHTLY => Calendar::daysBetween($first, $date) % 14 === 0,

            Cycle::MONTHLY => Calendar::weekOfMonth($first) === Calendar::weekOfMonth($date),

            Cycle::WEEKLY => true,
        };
    }

    /** The first bin night on or after the start date. */
    private function firstCollection(Bin $bin): DateTimeImmutable
    {
        $date = $this->startDate($bin);

        for ($days = 0; $days < 7 && $bin->house->binNight !== \strtolower($date->format('l')); $days++) {
            $date = $date->modify('+1 day');
        }

        return $date;
    }

    /** The start date is a calendar date, not an instant: read in the house's timezone it must stay the same day. */
    private function startDate(Bin $bin): DateTimeImmutable
    {
        return new DateTimeImmutable($bin->startDate->format('Y-m-d') . ' 12:00', $bin->house->timezone);
    }
}
