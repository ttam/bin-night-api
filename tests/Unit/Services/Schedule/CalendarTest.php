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

    public static function getSendInstants(): iterable
    {
        yield 'An ordinary time' => ['2026-11-03', '09:00:00', 'Australia/Melbourne', '2026-11-02 22:00'];

        yield 'Clocks go back: the first of two showings, Los Angeles' => ['2026-11-01', '01:30:00', 'America/Los_Angeles', '2026-11-01 08:30'];

        yield 'Clocks go back: the first of two showings, Melbourne' => ['2026-04-05', '02:30:00', 'Australia/Melbourne', '2026-04-04 15:30'];

        yield 'Clocks go back: the first of two showings, Lord Howe (a 30 minute change)' => ['2026-04-05', '01:45:00', 'Australia/Lord_Howe', '2026-04-04 14:45'];

        yield 'Clocks go forward: a time that does not exist, Melbourne 02:30' => ['2026-10-04', '02:30:00', 'Australia/Melbourne', '2026-10-03 16:30'];

        yield 'Clocks go forward: the first skipped minute, Melbourne 02:00' => ['2026-10-04', '02:00:00', 'Australia/Melbourne', '2026-10-03 16:00'];

        yield 'Clocks go forward: Los Angeles 02:30' => ['2026-03-08', '02:30:00', 'America/Los_Angeles', '2026-03-08 10:30'];

        yield 'Clocks go forward: Lord Howe 02:15 (a 30 minute change)' => ['2026-10-04', '02:15:00', 'Australia/Lord_Howe', '2026-10-03 15:45'];

        yield 'Clocks go forward at midnight, Havana 00:30' => ['2026-03-08', '00:30:00', 'America/Havana', '2026-03-08 05:30'];

        yield 'The hour before clocks go forward is untouched' => ['2026-10-04', '01:45:00', 'Australia/Melbourne', '2026-10-03 15:45'];

        yield 'The hour after clocks go forward is untouched' => ['2026-10-04', '03:00:00', 'Australia/Melbourne', '2026-10-03 16:00'];

        yield 'A zone 45 minutes off the hour, Eucla' => ['2026-06-01', '09:00:00', 'Australia/Eucla', '2026-06-01 00:15'];

        yield 'A zone 45 minutes off the hour, Kathmandu' => ['2026-06-01', '09:00:00', 'Asia/Kathmandu', '2026-06-01 03:15'];

        yield 'A zone 45 minutes off the hour, Chatham Islands' => ['2026-06-01', '09:00:00', 'Pacific/Chatham', '2026-05-31 20:15'];
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

    #[DataProvider('getSendInstants')]
    public function testSendInstant(string $date, string $time, string $timezone, string $expected): void
    {
        $instant = Calendar::sendInstant($date, $time, new DateTimeZone($timezone));

        self::assertSame($expected, $instant->setTimezone($utc = new DateTimeZone('UTC'))->format('Y-m-d H:i'));
        self::assertSame('UTC', $utc->getName());
    }

    public function testSendInstantIsOneRealMomentForEveryTimeOnEveryDayOnWhichClocksChange(): void
    {
        $checked = 0;

        foreach (DateTimeZone::listIdentifiers() as $name) {
            $timezone = new DateTimeZone($name);

            foreach (\array_slice($timezone->getTransitions(\strtotime('2026-01-01 UTC'), \strtotime('2026-12-31 UTC')), 1) as $transition) {
                $date = new DateTimeImmutable('@' . $transition['ts'])->setTimezone($timezone)->format('Y-m-d');
                $previousExact = null;

                for ($minutes = 0; $minutes < 1440; $minutes += 15) {
                    $time = \sprintf('%02d:%02d:00', \intdiv($minutes, 60), $minutes % 60);
                    $instant = Calendar::sendInstant($date, $time, $timezone);
                    $shows = $instant->setTimezone($timezone)->format('Y-m-d H:i:s');
                    $label = \sprintf('%s %s %s', $name, $date, $time);

                    if ($shows === $date . ' ' . $time) {
                        // A time that exists is sent when the clock shows it, and the times that exist stay in order.
                        self::assertTrue($previousExact === null || $instant > $previousExact, $label);
                        $previousExact = $instant;
                    } else {
                        // A time that does not exist is sent later, by the length of the gap: no more than two hours.
                        $late = \strtotime($shows . ' UTC') - \strtotime($date . ' ' . $time . ' UTC');
                        self::assertTrue($late > 0 && $late <= 7200, $label . ' shows ' . $shows);
                    }

                    $checked++;
                }
            }
        }

        self::assertGreaterThan(5000, $checked);
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
