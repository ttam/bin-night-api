<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Auth\AuthenticatedUser;
use App\Models\Bin;
use App\Models\Notification;
use App\Repositories\Interfaces\BinRepositoryInterface;
use App\Repositories\ScheduledBinRepository;
use App\Services\Clock\KnownClock;
use App\Services\Schedule\Interfaces\ScheduleServiceInterface;
use App\Services\Schedule\ScheduleService;
use DateTimeImmutable;
use Illuminate\Support\Collection;
use Tests\Unit\TestCase;

final class ScheduledBinRepositoryTest extends TestCase
{
    public function testGetBinsForNotificationPassesThrough(): void
    {
        $binRepository = $this->createStub(BinRepositoryInterface::class);
        $binRepository->method('getBinsForNotification')->willReturn(new Collection([
            new Bin()->setAttribute('name', 'example'),
        ]));

        self::assertSame('example', new ScheduledBinRepository(
            $binRepository,
            new KnownClock(),
            new ScheduleService(),
        )->getBinsForNotification(new Notification(), new DateTimeImmutable())->first()->name);
    }

    public function testGetBinsForUserDecoratesCorrectly(): void
    {
        $binRepository = $this->createStub(BinRepositoryInterface::class);
        $binRepository->method('getBinsForUser')->willReturn(new Collection([new Bin()]));

        $scheduleService = $this->createStub(ScheduleServiceInterface::class);
        $scheduleService->method('computeNextBinNight')->willReturn(new DateTimeImmutable('1985-10-26'));

        $user = new AuthenticatedUser(['sub' => 'example']);

        self::assertSame('1985-10-26', new ScheduledBinRepository(
            $binRepository,
            new KnownClock(),
            $scheduleService,
        )->getBinsForUser($user)->first()->computedNextBinNight->format('Y-m-d'));
    }
}
