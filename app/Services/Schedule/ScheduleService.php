<?php

declare(strict_types=1);

namespace App\Services\Schedule;

use App\Enums\Cycle;
use App\Models\Bin;
use App\Services\Schedule\Interfaces\ScheduleServiceInterface;
use DateTimeImmutable;
use DateTimeInterface;

final class ScheduleService implements ScheduleServiceInterface
{
    public function computeNextBinNight(Bin $bin, DateTimeInterface $now): ?DateTimeInterface
    {
        $date = DateTimeImmutable::createFromInterface($now)
            ->setTimezone($bin->house->timezone)
            ->setTime(12, 0);

        // Search up to a year ahead. More than enough for weekly/fortnightly/monthly.
        for ($i = 0; $i <= 370; $i++) {
            if ($this->matchesDate($bin, $date)) {
                return $date->setTime(0, 0);
            }

            $date = $date->modify('+1 day');
        }

        return null;
    }

    private function matchesDate(Bin $bin, DateTimeImmutable $date): bool
    {
        if ($bin->house->binNight !== \strtolower($date->format('l'))) {
            return false;
        }

        return match ($bin->cycle) {
            Cycle::FORTNIGHTLY => $this->isFortnightlyMatch($bin, $date),

            Cycle::MONTHLY => $bin->weekOfMonth === $this->weekOfMonth($date),

            Cycle::WEEKLY => true,
        };
    }

    private function isFortnightlyMatch(Bin $bin, DateTimeImmutable $date): bool
    {
        $startDate = $bin->startDate
            ->setTimezone($date->getTimezone())
            ->setTime(12, 0);

        $days = $startDate->diff($date)->days;
        $weeks = \intdiv($days, 7);

        return $weeks % 2 === 0;
    }

    private function weekOfMonth(DateTimeImmutable $date): int
    {
        return \intval(\ceil(\intval($date->format('j') / 7)));
    }
}
