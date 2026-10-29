<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Auth\AuthenticatedUser;
use App\DTO\Bin\CreateBin;
use App\DTO\Bin\UpdateBin;
use App\Enums\Cycle;
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
    public function testCreateBinDecoratesCorrectly(): void
    {
        $user = new AuthenticatedUser(['sub' => 'example']);
        $data = new CreateBin('#00dd00', Cycle::WEEKLY, 'house-1', '#00dd00', 'Example', '1985-10-26');
        $bin = new Bin();

        $binRepository = $this->createMock(BinRepositoryInterface::class);
        $binRepository->expects(self::once())->method('createBin')->with($user, $data)->willReturn($bin);

        $scheduleService = $this->createStub(ScheduleServiceInterface::class);
        $scheduleService->method('computeNextBinNight')->willReturn(new DateTimeImmutable('1985-10-26'));

        $result = new ScheduledBinRepository(
            $binRepository,
            new KnownClock(),
            $scheduleService,
        )->createBin($user, $data);

        self::assertSame('1985-10-26', $result->computedNextBinNight->format('Y-m-d'));
    }

    public function testDeleteBinIsPassedThrough(): void
    {
        $bin = new Bin();

        $binRepository = $this->createMock(BinRepositoryInterface::class);
        $binRepository->expects(self::once())->method('deleteBin')->with($bin);

        new ScheduledBinRepository(
            $binRepository,
            new KnownClock(),
            $this->createStub(ScheduleServiceInterface::class),
        )->deleteBin($bin);
    }

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

    public function testUpdateBinDecoratesCorrectly(): void
    {
        $bin = new Bin();
        $data = new UpdateBin(['name' => 'Example']);

        $binRepository = $this->createMock(BinRepositoryInterface::class);
        $binRepository->expects(self::once())->method('updateBin')->with($bin, $data)->willReturn($bin);

        $scheduleService = $this->createStub(ScheduleServiceInterface::class);
        $scheduleService->method('computeNextBinNight')->willReturn(new DateTimeImmutable('1985-10-26'));

        $result = new ScheduledBinRepository(
            $binRepository,
            new KnownClock(),
            $scheduleService,
        )->updateBin($bin, $data);

        self::assertSame('1985-10-26', $result->computedNextBinNight->format('Y-m-d'));
    }
}
