<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Clock;

use App\Services\Clock\Clock;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ClockTest extends TestCase
{
    public function testNowReturnsExpectedDateTime(): void
    {
        self::assertEquals(
            new DateTimeImmutable()->format('Y-m-d H:i:s'),
            new Clock()->now()->format('Y-m-d H:i:s'),
            'Clock::now() should return the current time.'
        );
    }
}
