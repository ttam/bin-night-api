<?php

declare(strict_types=1);

namespace App\Services\Clock;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

final readonly class KnownClock implements ClockInterface
{
    public function __construct(private ?DateTimeImmutable $dateTime = null)
    {
    }

    public function now(): DateTimeImmutable
    {
        return $this->dateTime ?? new DateTimeImmutable('1985-10-26 01:21:00');
    }
}
