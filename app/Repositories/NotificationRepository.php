<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Notification;
use App\Repositories\Interfaces\NotificationRepositoryInterface;
use DateTimeInterface;
use Illuminate\Support\Collection;

final class NotificationRepository implements NotificationRepositoryInterface
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
}
