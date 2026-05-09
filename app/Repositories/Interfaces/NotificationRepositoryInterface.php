<?php

declare(strict_types=1);

namespace App\Repositories\Interfaces;

use DateTimeInterface;
use Illuminate\Support\Collection;

interface NotificationRepositoryInterface
{
    /** @return \Illuminate\Support\Collection<\App\Models\Notification> */
    public function getDueNotifications(DateTimeInterface $dateTime): Collection;
}
