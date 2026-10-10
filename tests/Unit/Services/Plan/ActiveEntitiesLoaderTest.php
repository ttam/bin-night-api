<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Plan;

use App\Services\Plan\ActiveEntities;
use App\Services\Plan\ActiveEntitiesLoader;
use App\Services\Plan\PlanLimits;
use PHPUnit\Framework\TestCase;

final class ActiveEntitiesLoaderTest extends TestCase
{
    public function testLoadsOnceAndRemembersTheResult(): void
    {
        $calls = 0;

        $loader = new ActiveEntitiesLoader(static function () use (&$calls): ActiveEntities {
            ++$calls;

            return new ActiveEntities(
                new PlanLimits(1, 2, 2, []),
                [],
                [],
                []
            );
        });

        self::assertSame($loader->get(), $loader->get());
        self::assertSame(1, $calls);
    }
}
