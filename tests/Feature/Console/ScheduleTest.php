<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Tests\TestCase;

final class ScheduleTest extends TestCase
{
    public function testNotificationsRunEveryHalfHourAndNeverOverlap(): void
    {
        // The schedule is only defined once the console starts.
        $this->app->make(Kernel::class)->all();

        $events = \array_values(\array_filter(
            $this->app->make(Schedule::class)->events(),
            static fn (Event $event): bool => \str_contains((string) $event->command, 'bin-night:trigger-notifications'),
        ));

        self::assertCount(1, $events);
        self::assertSame('*/30 * * * *', $events[0]->expression);
        self::assertTrue($events[0]->withoutOverlapping);
        self::assertSame(25, $events[0]->expiresAt);
    }
}
