<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Auth\AuthenticatedUser;
use App\DTO\Bin\CreateBin;
use App\DTO\Bin\UpdateBin;
use App\Models\Bin;
use App\Models\Notification;
use App\Repositories\Interfaces\BinRepositoryInterface;
use App\Services\Schedule\Interfaces\ScheduleServiceInterface;
use DateTimeImmutable;
use Illuminate\Support\Collection;
use Psr\Clock\ClockInterface;

final readonly class ScheduledBinRepository implements BinRepositoryInterface
{
    public function __construct(
        private BinRepositoryInterface $repository,
        private ClockInterface $clock,
        private ScheduleServiceInterface $schedule,
    ) {
    }

    public function createBin(AuthenticatedUser $authenticatedUser, CreateBin $data): Bin
    {
        return $this->withNextBinNight($this->repository->createBin($authenticatedUser, $data));
    }

    public function deleteBin(Bin $bin): void
    {
        $this->repository->deleteBin($bin);
    }

    /** @inheritDoc */
    public function getBinsForUser(AuthenticatedUser $user): Collection
    {
        return $this->repository
            ->getBinsForUser($user)
            ->each(fn (Bin $bin): Bin => $this->withNextBinNight($bin));
    }

    public function getBinsForNotification(Notification $notification, DateTimeImmutable $now): Collection
    {
        return $this->repository->getBinsForNotification($notification, $now);
    }

    public function updateBin(Bin $bin, UpdateBin $data): Bin
    {
        return $this->withNextBinNight($this->repository->updateBin($bin, $data));
    }

    private function withNextBinNight(Bin $bin): Bin
    {
        /** @var Bin */
        return $bin->setAttribute(
            'computed_next_bin_night',
            $this->schedule->computeNextBinNight($bin, $this->clock->now()),
        );
    }
}
