<?php

declare(strict_types=1);

namespace App\Services\Schedule\Interfaces;

use App\Models\Bin;
use App\Models\House;
use App\Models\Notification;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Collection;

interface ScheduleServiceInterface
{
    public function computeNextBinNight(Bin $bin, DateTimeInterface $now): ?DateTimeInterface;

    /**
     * Every bin night from today (in the house's timezone) on which something goes out, looking $weeks bin nights ahead.
     * A bin night with nothing going out is left out.
     *
     * @param \Illuminate\Support\Collection<int, \App\Models\Bin> $bins the bins of the house
     *
     * @return list<\App\DTO\Schedule\UpcomingCollection>
     */
    public function getUpcomingCollections(House $house, Collection $bins, DateTimeInterface $now, int $weeks): array;

    /**
     * @param \Illuminate\Support\Collection<int, \App\Models\Bin> $bins the bins of the notification's house
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Bin>
     */
    public function getBinsCollectedForNotification(Notification $notification, DateTimeImmutable $slot, Collection $bins): Collection;
}
