<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Auth\AuthenticatedUser;
use App\Models\Notification;
use App\Repositories\Interfaces\NotificationRepositoryInterface;
use DateTimeInterface;
use Illuminate\Support\Collection;

final readonly class NotificationRepository implements NotificationRepositoryInterface
{
    /** @inheritDoc */
    public function getDueNotifications(DateTimeInterface $dateTime): Collection
    {
        $dateTimeHouse = 'CONVERT_TZ(?, "+00:00", houses.timezone)';
        $notificationDateTime = \sprintf('CONCAT(DATE(%s), " ", notifications.send_time, ":00")', $dateTimeHouse);

        $condition = \sprintf('%s = %s', $notificationDateTime, $dateTimeHouse);

        return new Notification()->newQuery()
            ->select('notifications.*')
            ->join('houses', 'notifications.house_id', '=', 'houses.id')
            ->whereRaw($condition, [$dateTime, $dateTime])
            ->get();
    }

    /** @inheritDoc */
    public function getNotificationsForUser(AuthenticatedUser $user): Collection
    {
        return new Notification()->newQuery()
            ->select('notifications.*')
            ->join('users', 'users.id', '=', 'notifications.user_id')
            ->where('users.external_id', '=', $user->id)
            ->get();
    }
}
