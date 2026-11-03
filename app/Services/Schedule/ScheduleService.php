<?php

declare(strict_types=1);

namespace App\Services\Schedule;

use App\DTO\Schedule\UpcomingCollection;
use App\Models\Bin;
use App\Models\House;
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

    /** @inheritDoc */
    public function getUpcomingCollections(House $house, Collection $bins, DateTimeInterface $now, int $weeks): array
    {
        $bins->each(static fn (Bin $bin): Bin => $bin->setRelation('house', $house));

        $date = $this->onOrAfterBinNight(
            $house->binNight,
            DateTimeImmutable::createFromInterface($now)->setTimezone($house->timezone)->setTime(12, 0),
        );
        $collections = [];

        for ($week = 0; $week < $weeks; $week++) {
            $due = $bins->filter(fn (Bin $bin): bool => $this->isCollectedOn($bin, $date))->values();

            if ($due->isNotEmpty()) {
                $collections[] = new UpcomingCollection($date->setTime(0, 0), $house, $due);
            }

            $date = $date->modify('+7 days');
        }

        return $collections;
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

        $weeks = $bin->cycle->weeks();

        return $weeks === null
            ? Calendar::weekOfMonth($first) === Calendar::weekOfMonth($date)
            : Calendar::daysBetween($first, $date) % (7 * $weeks) === 0;
    }

    /** The first bin night on or after the start date. */
    private function firstCollection(Bin $bin): DateTimeImmutable
    {
        return $this->onOrAfterBinNight($bin->house->binNight, $this->startDate($bin));
    }

    /** The first day on or after $date that is a $binNight (a lower case weekday); $date itself if there is none. */
    private function onOrAfterBinNight(string $binNight, DateTimeImmutable $date): DateTimeImmutable
    {
        for ($days = 0; $days < 7 && $binNight !== \strtolower($date->format('l')); $days++) {
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
