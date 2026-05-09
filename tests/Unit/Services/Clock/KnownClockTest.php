<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Clock;

use App\Services\Clock\KnownClock;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class KnownClockTest extends TestCase
{
    public function testNowReturnsExpectedDateTime(): void
    {
        self::assertEquals(
            new DateTimeImmutable('1985-10-26 01:21:00'),
            new KnownClock()->now(),
            'KnownClock::now() should return a specific time.'
        );
    }

    public function testNowReturnsProvidedDateTime(): void
    {
        self::assertEquals(
            new DateTimeImmutable('2015-10-22 01:28:00'),
            new KnownClock(new DateTimeImmutable('2015-10-22 01:28:00'))->now(),
            'KnownClock::now() should return a specific time.'
        );
    }
}
