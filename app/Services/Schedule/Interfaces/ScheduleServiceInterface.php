<?php

declare(strict_types=1);

namespace App\Services\Schedule\Interfaces;

use App\Models\Bin;
use DateTimeInterface;

interface ScheduleServiceInterface
{
    public function computeNextBinNight(Bin $bin, DateTimeInterface $now): ?DateTimeInterface;
}
