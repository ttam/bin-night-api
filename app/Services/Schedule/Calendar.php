<?php

declare(strict_types=1);

namespace App\Services\Schedule;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * The calendar sums behind every collection date, in one place so the API's next bin night and the notifications can
 * never disagree. Everything here works on calendar dates: the time and timezone of what is passed in are ignored.
 */
final class Calendar
{
    /**
     * The UTC instant at which something set for a local date and time is due, which is always exactly one instant.
     *
     * Most times have one: 09:00 on a date is one moment. On the night clocks go back a time has two (01:30 happens
     * twice), and the earlier one is used, so it is sent once. On the night clocks go forward a time has none (02:30
     * never happens), and it is read as if the clocks had not changed, one gap-length later (03:30). PHP's own reading
     * of a repeated time differs from one zone to the next, so the repeated case is not left to it.
     */
    public static function sendInstant(string $date, string $time, DateTimeZone $timezone): DateTimeImmutable
    {
        $local = \sprintf('%s %s', $date, $time);
        $utc = new DateTimeZone('UTC');
        $asIfUtc = new DateTimeImmutable($local, $utc);

        // The offsets in force a day either side cover the two sides of any change of clock that day.
        $offsets = \array_unique([
            $timezone->getOffset($asIfUtc->modify('-1 day')),
            $timezone->getOffset($asIfUtc),
            $timezone->getOffset($asIfUtc->modify('+1 day')),
        ]);

        $instants = [];

        foreach ($offsets as $offset) {
            $instant = $asIfUtc->modify(\sprintf('%+d seconds', -$offset));

            // An offset only counts if it really gives the time asked for.
            if ($instant->setTimezone($timezone)->format('Y-m-d H:i:s') === $local) {
                $instants[] = $instant;
            }
        }

        return $instants === [] ? new DateTimeImmutable($local, $timezone)->setTimezone($utc) : \min($instants);
    }

    /** Which occurrence of its weekday a date is in its month: days 1 to 7 are week 1, 8 to 14 week 2, and 29 to 31 week 5. */
    public static function weekOfMonth(DateTimeInterface $date): int
    {
        return \intdiv(\intval($date->format('j')) - 1, 7) + 1;
    }

    /** How many calendar days it is from one date to the other (negative if the second is earlier). Daylight saving does not change the count. */
    public static function daysBetween(DateTimeInterface $from, DateTimeInterface $to): int
    {
        return self::dayNumber($to) - self::dayNumber($from);
    }

    /** Days since 1970-01-01 of the date's calendar day. */
    private static function dayNumber(DateTimeInterface $date): int
    {
        return \intdiv(new DateTimeImmutable($date->format('Y-m-d'), new DateTimeZone('UTC'))->getTimestamp(), 86400);
    }
}
