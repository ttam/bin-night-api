<?php

declare(strict_types=1);

namespace App\Services\Schedule\Interfaces;

use App\Models\Bin;
use App\Models\Notification;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Collection;

interface ScheduleServiceInterface
{
    public function computeNextBinNight(Bin $bin, DateTimeInterface $now): ?DateTimeInterface;

    /**
     * @param \Illuminate\Support\Collection<int, \App\Models\Bin> $bins the bins of the notification's house
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Bin>
     */
    public function getBinsCollectedForNotification(Notification $notification, DateTimeImmutable $slot, Collection $bins): Collection;
}
