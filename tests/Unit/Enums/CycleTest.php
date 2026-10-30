<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\Cycle;
use PHPUnit\Framework\TestCase;

final class CycleTest extends TestCase
{
    public function testWeeksIsNullForAMonthlyCycle(): void
    {
        self::assertNull(Cycle::MONTHLY->weeks());
    }

    public function testWeeksIsTheNumberOfWeeksBetweenCollections(): void
    {
        self::assertSame(1, Cycle::WEEKLY->weeks());
        self::assertSame(2, Cycle::FORTNIGHTLY->weeks());
        self::assertSame(4, Cycle::FOUR_WEEKLY->weeks());
    }
}
