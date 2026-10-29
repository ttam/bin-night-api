<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Schedule;

use App\Services\Schedule\Calendar;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CalendarTest extends TestCase
{
    public static function getDaysBetween(): iterable
    {
        yield 'The same day' => ['1985-11-06', '1985-11-06', 0];

        yield 'The next day' => ['1985-11-06', '1985-11-07', 1];

        yield 'A fortnight on' => ['1985-11-06', '1985-11-20', 14];

        yield 'Going back a day' => ['1985-11-06', '1985-11-05', -1];

        yield 'Going back a fortnight' => ['1985-11-20', '1985-11-06', -14];

        yield 'Over New Year' => ['1985-12-30', '1986-01-06', 7];

        yield 'Over a leap day' => ['1984-02-27', '1984-03-05', 7];

        yield 'Over the end of daylight saving' => ['1985-10-26', '1985-10-28', 2];

        yield 'Over a year and a bit' => ['1984-12-31', '1986-01-06', 371];

        yield 'Before 1970' => ['1955-11-12', '1955-11-26', 14];
    }

    public static function getWeeksOfMonth(): iterable
    {
        yield 'The 1st' => ['1985-10-01', 1];

        yield 'The 7th' => ['1985-10-07', 1];

        yield 'The 8th' => ['1985-10-08', 2];

        yield 'The 14th' => ['1985-10-14', 2];

        yield 'The 15th' => ['1985-10-15', 3];

        yield 'The 21st' => ['1985-10-21', 3];

        yield 'The 22nd' => ['1985-10-22', 4];

        yield 'The 28th' => ['1985-10-28', 4];

        yield 'The 29th' => ['1985-10-29', 5];

        yield 'The 30th' => ['1985-10-30', 5];

        yield 'The 31st' => ['1985-10-31', 5];

        yield 'The 29th of a leap February' => ['1984-02-29', 5];
    }

    #[DataProvider('getDaysBetween')]
    public function testDaysBetweenCountsCalendarDays(string $from, string $to, int $expected): void
    {
        self::assertSame($expected, Calendar::daysBetween(new DateTimeImmutable($from), new DateTimeImmutable($to)));
    }

    public function testDaysBetweenIgnoresTimeAndTimezone(): void
    {
        $from = new DateTimeImmutable('1985-11-04 23:30', new DateTimeZone('America/Los_Angeles'));
        $to = new DateTimeImmutable('1985-11-06 00:30', new DateTimeZone('Asia/Tokyo'));

        self::assertSame(2, Calendar::daysBetween($from, $to));
    }

    #[DataProvider('getWeeksOfMonth')]
    public function testWeekOfMonth(string $date, int $expected): void
    {
        self::assertSame($expected, Calendar::weekOfMonth(new DateTimeImmutable($date)));
    }

    public function testWeekOfMonthIgnoresTimeAndTimezone(): void
    {
        self::assertSame(2, Calendar::weekOfMonth(new DateTimeImmutable('1985-10-08 23:59', new DateTimeZone('America/Los_Angeles'))));
    }
}
