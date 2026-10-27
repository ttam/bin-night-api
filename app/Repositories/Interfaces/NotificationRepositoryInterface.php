<?php

declare(strict_types=1);

namespace App\Repositories\Interfaces;

use App\Auth\AuthenticatedUser;
use App\DTO\Notification\CreateNotification;
use App\DTO\Notification\UpdateNotification;
use App\Models\Notification;
use DateTimeInterface;
use Illuminate\Support\Collection;

interface NotificationRepositoryInterface
{
    public function createNotification(AuthenticatedUser $authenticatedUser, CreateNotification $data): Notification;

    /** @return \Illuminate\Support\Collection<\App\Models\Notification> */
    public function getDueNotifications(DateTimeInterface $dateTime): Collection;

    /** @return \Illuminate\Support\Collection<\App\Models\Notification> */
    public function getNotificationsForUser(AuthenticatedUser $user): Collection;

    public function updateNotification(Notification $notification, UpdateNotification $data): Notification;
}
