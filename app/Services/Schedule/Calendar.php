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
