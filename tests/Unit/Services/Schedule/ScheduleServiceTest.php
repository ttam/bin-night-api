<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Schedule;

use App\Enums\Cycle;
use App\Models\Bin;
use App\Models\House;
use App\Models\Notification;
use App\Services\Schedule\ScheduleService;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * 1985-10-02 was a Wednesday, so the Wednesdays that follow are 10-09, 10-16, 10-23, 10-30, 11-06, 11-13, 11-20, 11-27,
 * 12-04, 12-11, 12-18, 12-25 and, in 1986, 01-01, 01-08, 01-15, 01-22 and 01-29.
 */
final class ScheduleServiceTest extends TestCase
{
    /** Each start date, with the dates it gives from 1 October 1985, in every timezone. */
    public static function getFortnightlyStartDates(): iterable
    {
        $sinceTheSixth = ['1985-11-06', '1985-11-20', '1985-12-04', '1985-12-18', '1986-01-01', '1986-01-15', '1986-01-29'];
        $sinceTheThirteenth = ['1985-11-13', '1985-11-27', '1985-12-11', '1985-12-25', '1986-01-08', '1986-01-22', '1986-02-05'];

        foreach (['UTC', 'Australia/Melbourne', 'America/Los_Angeles', 'Asia/Tokyo', 'Pacific/Kiritimati', 'Pacific/Pago_Pago'] as $timezone) {
            // The first bin night on or after the start date is the 6th for a Monday or Wednesday start...
            yield $timezone . ', Monday start' => ['1985-11-04', $sinceTheSixth, $timezone];

            yield $timezone . ', Wednesday start' => ['1985-11-06', $sinceTheSixth, $timezone];

            // ...and the 13th for a start on Thursday, Friday or Sunday, which are just after one.
            yield $timezone . ', Thursday start' => ['1985-11-07', $sinceTheThirteenth, $timezone];

            yield $timezone . ', Sunday start' => ['1985-11-10', $sinceTheThirteenth, $timezone];
        }
    }

    /** Timezones either side of UTC, including the extremes and ones with daylight saving. */
    public static function getTimezones(): iterable
    {
        foreach (['UTC', 'Australia/Melbourne', 'America/Los_Angeles', 'Asia/Tokyo', 'Pacific/Kiritimati', 'Pacific/Pago_Pago'] as $timezone) {
            yield $timezone => [$timezone];
        }
    }

    public function testBinsCollectedForNotificationAreNoneWhenTheLeadDayIsNotBinNight(): void
    {
        $house = $this->house('sunday', 'America/Los_Angeles');
        $bins = new Collection([$this->bin(Cycle::WEEKLY, '1985-10-26', $house)]);
        $slot = new DateTimeImmutable('1985-10-27 01:30', new DateTimeZone('UTC'));

        self::assertCount(0, new ScheduleService()->getBinsCollectedForNotification($this->notification($house, 0), $slot, $bins));
    }

    public function testBinsCollectedForNotificationAreThoseOnTheLeadDay(): void
    {
        $house = $this->house('sunday', 'America/Los_Angeles');
        $weekly = $this->bin(Cycle::WEEKLY, '1985-10-26', $house);
        $fortnightlyThisWeek = $this->bin(Cycle::FORTNIGHTLY, '1985-10-27', $house);
        $fortnightlyOtherWeek = $this->bin(Cycle::FORTNIGHTLY, '1985-10-20', $house);
        $monthlySecondSunday = $this->bin(Cycle::MONTHLY, '1985-10-13', $house);
        $startsThatDay = $this->bin(Cycle::WEEKLY, '1985-10-27', $house);
        $startsAfterwards = $this->bin(Cycle::WEEKLY, '1985-10-28', $house);
        $bins = new Collection([$weekly, $fortnightlyThisWeek, $fortnightlyOtherWeek, $monthlySecondSunday, $startsThatDay, $startsAfterwards]);

        // 01:30 UTC on the 27th is 18:30 on Saturday the 26th in Los Angeles. One day ahead is Sunday the 27th.
        $slot = new DateTimeImmutable('1985-10-27 01:30', new DateTimeZone('UTC'));

        $due = new ScheduleService()->getBinsCollectedForNotification($this->notification($house, 1), $slot, $bins);

        self::assertSame([$weekly, $fortnightlyThisWeek, $startsThatDay], $due->all());
    }

    public function testBinsCollectedForNotificationUseTheHousesDateRatherThanUtcs(): void
    {
        $house = $this->house('saturday', 'America/Los_Angeles');
        $bins = new Collection([$this->bin(Cycle::WEEKLY, '1985-10-26', $house)]);

        // 01:30 UTC on Sunday the 27th is still Saturday the 26th in Los Angeles, so lead day 0 is bin night.
        $slot = new DateTimeImmutable('1985-10-27 01:30', new DateTimeZone('UTC'));

        self::assertCount(1, new ScheduleService()->getBinsCollectedForNotification($this->notification($house, 0), $slot, $bins));
    }

    /** @param list<string> $expected */
    #[DataProvider('getFortnightlyStartDates')]
    public function testComputeNextBinNightForAFortnightlyBinCountsFromItsFirstCollection(string $startDate, array $expected, string $timezone): void
    {
        $bin = $this->bin(Cycle::FORTNIGHTLY, $startDate, $this->house('wednesday', $timezone));

        self::assertSame($expected, $this->sequence($bin, '1985-10-01 00:00', 7, $timezone));
    }

    public function testComputeNextBinNightForAFourWeeklyBinGoesOutEveryTwentyEightDaysFromItsFirstCollection(): void
    {
        $house = $this->house('wednesday', 'Australia/Melbourne');

        // A Thursday start is followed by Wednesday the 13th, then every 28 days, which drifts through the weeks of the month.
        self::assertSame(['1985-11-13', '1985-12-11', '1986-01-08', '1986-02-05', '1986-03-05'], $this->sequence($this->bin(Cycle::FOUR_WEEKLY, '1985-11-07', $house), '1985-10-01 00:00', 5));
        self::assertSame(['1985-11-06', '1985-12-04', '1986-01-01', '1986-01-29', '1986-02-26'], $this->sequence($this->bin(Cycle::FOUR_WEEKLY, '1985-11-06', $house), '1985-10-01 00:00', 5));
    }

    public function testComputeNextBinNightForAMonthlyBinInTheFifthWeekSkipsMonthsWithoutOne(): void
    {
        $bin = $this->bin(Cycle::MONTHLY, '1985-10-30', $this->house('wednesday', 'Australia/Melbourne'));

        self::assertSame(['1985-10-30', '1986-01-29', '1986-04-30', '1986-07-30', '1986-10-29', '1986-12-31'], $this->sequence($bin, '1985-10-01 00:00', 6));
    }

    public function testComputeNextBinNightForAMonthlyBinIsTheSameWeekdayOfTheMonthEachMonth(): void
    {
        $house = $this->house('wednesday', 'America/Los_Angeles');

        self::assertSame(['1985-10-02', '1985-11-06', '1985-12-04', '1986-01-01'], $this->sequence($this->bin(Cycle::MONTHLY, '1985-10-02', $house), '1985-10-01 00:00', 4, 'America/Los_Angeles'));
        self::assertSame(['1985-10-09', '1985-11-13', '1985-12-11', '1986-01-08'], $this->sequence($this->bin(Cycle::MONTHLY, '1985-10-09', $house), '1985-10-01 00:00', 4, 'America/Los_Angeles'));
        self::assertSame(['1985-10-16', '1985-11-20', '1985-12-18', '1986-01-15'], $this->sequence($this->bin(Cycle::MONTHLY, '1985-10-16', $house), '1985-10-01 00:00', 4, 'America/Los_Angeles'));
        self::assertSame(['1985-10-23', '1985-11-27', '1985-12-25', '1986-01-22'], $this->sequence($this->bin(Cycle::MONTHLY, '1985-10-23', $house), '1985-10-01 00:00', 4, 'America/Los_Angeles'));
    }

    public function testComputeNextBinNightForAMonthlyBinTakesItsWeekFromTheFirstBinNightOnOrAfterTheStartDate(): void
    {
        $house = $this->house('wednesday', 'Australia/Melbourne');

        // Thursday the 3rd is followed by the second Wednesday, the 9th.
        self::assertSame(['1985-10-09', '1985-11-13', '1985-12-11'], $this->sequence($this->bin(Cycle::MONTHLY, '1985-10-03', $house), '1985-10-01 00:00', 3));

        // Thursday the 31st is followed by the first Wednesday of November, not a fifth Wednesday.
        self::assertSame(['1985-11-06', '1985-12-04', '1986-01-01'], $this->sequence($this->bin(Cycle::MONTHLY, '1985-10-31', $house), '1985-10-01 00:00', 3));
    }

    public function testComputeNextBinNightForAWeeklyBinIgnoresTheWeeksOfItsStartDate(): void
    {
        $bin = $this->bin(Cycle::WEEKLY, '1955-11-12', $this->house('wednesday', 'Australia/Melbourne'));

        self::assertSame('1985-10-23', $this->next($bin, '1985-10-21 10:00'));
        self::assertSame('1985-10-23', $this->next($bin, '1985-10-23 10:00'));
        self::assertSame('1985-10-30', $this->next($bin, '1985-10-24 10:00'));
    }

    public function testComputeNextBinNightIsTheFirstCollectionBeforeTheStartDate(): void
    {
        $house = $this->house('wednesday', 'Australia/Melbourne');

        // Nothing goes out before the start date, even on a bin night.
        self::assertSame('1985-11-13', $this->next($this->bin(Cycle::WEEKLY, '1985-11-08', $house), '1985-10-01 10:00'));
        self::assertSame('1985-11-06', $this->next($this->bin(Cycle::WEEKLY, '1985-11-06', $house), '1985-10-01 10:00'));
        self::assertSame('1985-10-02', $this->next($this->bin(Cycle::MONTHLY, '1985-10-02', $house), '1985-09-01 10:00'));
        self::assertSame('1985-11-13', $this->next($this->bin(Cycle::FORTNIGHTLY, '1985-11-08', $house), '1985-10-01 10:00'));
        self::assertSame('1985-11-13', $this->next($this->bin(Cycle::FOUR_WEEKLY, '1985-11-08', $house), '1985-10-01 10:00'));
    }

    #[DataProvider('getTimezones')]
    public function testComputeNextBinNightNeverGivesTwoWeeksRunningForAFortnightlyBin(string $timezone): void
    {
        foreach (['1985-11-04', '1985-11-06', '1985-11-07', '1985-11-10'] as $startDate) {
            $bin = $this->bin(Cycle::FORTNIGHTLY, $startDate, $this->house('wednesday', $timezone));
            $dates = $this->sequence($bin, '1985-10-01 00:00', 10, $timezone);

            foreach (\array_keys($dates) as $i) {
                if ($i > 0) {
                    self::assertSame(14, \intval(new DateTimeImmutable($dates[$i - 1])->diff(new DateTimeImmutable($dates[$i]))->days), \sprintf('%s, start %s', $timezone, $startDate));
                }
            }
        }
    }

    public function testComputeNextBinNightReachesAStartDateMoreThanAYearAway(): void
    {
        $bin = $this->bin(Cycle::WEEKLY, '1987-01-07', $this->house('wednesday', 'Australia/Melbourne'));

        self::assertSame('1987-01-07', $this->next($bin, '1985-10-01 10:00'));
    }

    public function testComputeNextBinNightReturnsNullWithInvalidBinNight(): void
    {
        $bin = $this->bin(Cycle::WEEKLY, '1955-11-12', $this->house('invalid', 'America/Los_Angeles'));

        self::assertNull(new ScheduleService()->computeNextBinNight($bin, new DateTimeImmutable('1985-10-21')));
    }

    private function bin(Cycle $cycle, string $startDate, House $house): Bin
    {
        $bin = new Bin()
            ->setRawAttributes(['start_date' => new DateTimeImmutable($startDate, new DateTimeZone('UTC'))])
            ->setRelation('house', $house);
        $bin->cycle = $cycle;

        return $bin;
    }

    private function house(string $binNight, string $timezone): House
    {
        return new House()->setAttribute('bin_night', $binNight)->setAttribute('timezone', $timezone);
    }

    private function next(Bin $bin, string $now, string $timezone = 'UTC'): ?string
    {
        return new ScheduleService()
            ->computeNextBinNight($bin, new DateTimeImmutable($now, new DateTimeZone($timezone)))
            ?->format('Y-m-d');
    }

    private function notification(House $house, int $leadDays): Notification
    {
        return new Notification()->setRelation('house', $house)->setAttribute('lead_days', $leadDays);
    }

    /** @return list<string> */
    private function sequence(Bin $bin, string $from, int $count, string $timezone = 'UTC'): array
    {
        $dates = [];
        $now = new DateTimeImmutable($from, new DateTimeZone($timezone));

        for ($i = 0; $i < $count; $i++) {
            $next = new ScheduleService()->computeNextBinNight($bin, $now);
            self::assertInstanceOf(DateTimeInterface::class, $next);

            $dates[] = $next->format('Y-m-d');
            $now = DateTimeImmutable::createFromInterface($next)->modify('+1 day');
        }

        return $dates;
    }
}
