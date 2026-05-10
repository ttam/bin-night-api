<?php

declare(strict_types=1);

namespace App\Repositories\Interfaces;

use App\Auth\AuthenticatedUser;
use App\Models\Notification;
use DateTimeImmutable;
use Illuminate\Support\Collection;

interface BinRepositoryInterface
{
    /** @return \Illuminate\Support\Collection<\App\Models\Bin> */
    public function getBinsForNotification(Notification $notification, DateTimeImmutable $now): Collection;

    /** @return \Illuminate\Support\Collection<\App\Models\Bin> */
    public function getBinsForUser(AuthenticatedUser $user): Collection;
}
