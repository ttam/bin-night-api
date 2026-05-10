<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Schedule;

use App\Enums\Cycle;
use App\Models\Bin;
use App\Models\House;
use App\Services\Schedule\ScheduleService;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ScheduleServiceTest extends TestCase
{
    public static function getComputeData(): iterable
    {
        yield 'Fortnightly - Future' => [Cycle::FORTNIGHTLY, '1985-11-02', '1985-10-21'];
        yield 'Fortnightly - Today' => [Cycle::FORTNIGHTLY, '1985-11-02', '1985-11-02'];

        yield 'Monthly - Future' => [Cycle::MONTHLY, '1985-10-19', '1985-10-01'];
        yield 'Monthly - Today' => [Cycle::MONTHLY, '1985-10-19', '1985-10-19'];

        yield 'Weekly - Future' => [Cycle::WEEKLY, '1985-10-26', '1985-10-21'];
        yield 'Weekly - Today' => [Cycle::WEEKLY, '1985-10-26', '1985-10-26'];
    }

    #[DataProvider('getComputeData')]
    public function testComputeNextBinNight(Cycle $cycle, string $expected, string $today): void
    {
        $house = new House()
            ->setAttribute('bin_night', 'saturday')
            ->setAttribute('timezone', 'America/Los_Angeles');

        $bin = new Bin()
            ->setRawAttributes(['start_date' => new DateTimeImmutable('1955-11-12')])
            ->setRelation('house', $house)
            ->setAttribute('week_of_month', 2);
        $bin->cycle = $cycle;

        $actual = new ScheduleService()
            ->computeNextBinNight($bin, new DateTimeImmutable($today))
            ->format('Y-m-d');

        self::assertSame($expected, $actual);
    }

    public function testComputeNextBinNightReturnsNullWithInvalidDate(): void
    {
        $house = new House()
            ->setAttribute('bin_night', 'invalid')
            ->setAttribute('timezone', 'America/Los_Angeles');

        $bin = new Bin()
            ->setRawAttributes(['start_date' => new DateTimeImmutable('1955-11-12')])
            ->setRelation('house', $house)
            ->setAttribute('cycle', 'weekly');

        self::assertNull(new ScheduleService()->computeNextBinNight($bin, new DateTimeImmutable('1985-10-21')));
    }
}
