<?php

declare(strict_types=1);

namespace App\DTO\Schedule;

use App\Models\Bin;
use App\Models\House;
use DateTimeImmutable;
use Illuminate\Support\Collection;

/** One bin night at a house, and the bins that go out on it. */
final readonly class UpcomingCollection
{
    /** @param \Illuminate\Support\Collection<int, \App\Models\Bin> $bins */
    public function __construct(
        public DateTimeImmutable $date,
        public House $house,
        public Collection $bins,
    ) {
    }
}
