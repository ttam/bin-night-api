<?php

declare(strict_types=1);

namespace App\Repositories\Interfaces;

use App\Auth\AuthenticatedUser;
use App\DTO\Notification\CreateNotification;
use App\DTO\Notification\UpdateNotification;
use App\Models\Notification;
use DateTimeImmutable;
use Illuminate\Support\Collection;

interface NotificationRepositoryInterface
{
    public function createNotification(AuthenticatedUser $authenticatedUser, CreateNotification $data): Notification;

    public function deleteNotification(Notification $notification): void;

    /**
     * The notifications to send in a window of time, each with the instant it is due. Something set for 09:00 is due at
     * the one moment that is 09:00 in its house's timezone on each local date, so it is due once a day however the clocks
     * change. A notification whose delivery for that instant has already been sent is left out.
     *
     * @param DateTimeImmutable $after exclusive
     * @param DateTimeImmutable $until inclusive
     *
     * @return \Illuminate\Support\Collection<int, \App\DTO\Notification\DueNotification> oldest first
     */
    public function getDueNotifications(DateTimeImmutable $after, DateTimeImmutable $until): Collection;

    /** @return \Illuminate\Support\Collection<int, \App\Models\Notification> */
    public function getNotificationsForUser(AuthenticatedUser $user): Collection;

    public function updateNotification(Notification $notification, UpdateNotification $data): Notification;
}
