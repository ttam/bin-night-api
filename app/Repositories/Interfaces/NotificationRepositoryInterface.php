<?php

declare(strict_types=1);

namespace App\Repositories\Interfaces;

use App\Auth\AuthenticatedUser;
use DateTimeInterface;
use Illuminate\Support\Collection;

interface NotificationRepositoryInterface
{
    /** @return \Illuminate\Support\Collection<\App\Models\Notification> */
    public function getDueNotifications(DateTimeInterface $dateTime): Collection;

    /** @return \Illuminate\Support\Collection<\App\Models\Notification> */
    public function getNotificationsForUser(AuthenticatedUser $user): Collection;
}
