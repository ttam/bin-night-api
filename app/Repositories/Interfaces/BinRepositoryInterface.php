<?php

declare(strict_types=1);

namespace App\Repositories\Interfaces;

use App\Models\Notification;
use DateTimeImmutable;
use Illuminate\Support\Collection;

interface BinRepositoryInterface
{
    /** @return \Illuminate\Support\Collection<\App\Models\Bin> */
    public function getBinsForNotification(Notification $notification, DateTimeImmutable $now): Collection;
}
