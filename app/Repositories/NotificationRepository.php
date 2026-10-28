<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Auth\AuthenticatedUser;
use App\DTO\Notification\CreateNotification;
use App\DTO\Notification\UpdateNotification;
use App\Models\Notification;
use App\Models\User;
use App\Repositories\Interfaces\NotificationRepositoryInterface;
use DateTimeInterface;
use Illuminate\Support\Collection;

final readonly class NotificationRepository implements NotificationRepositoryInterface
{
    public function createNotification(AuthenticatedUser $authenticatedUser, CreateNotification $data): Notification
    {
        $userId = User::query()->where('external_id', $authenticatedUser->id)->value('id');

        $notification = new Notification()->fill([
            'lead_days' => $data->leadDays,
            'send_time' => $data->sendTime,
            'target' => $data->target,
            'type' => $data->type->value,
        ]);

        $notification->setAttribute('house_id', $data->houseId);
        $notification->setAttribute('user_id', $userId);
        $notification->save();

        return $notification;
    }

    /** @inheritDoc */
    public function getDueNotifications(DateTimeInterface $dateTime): Collection
    {
        $dateTimeHouse = 'CONVERT_TZ(?, "+00:00", houses.timezone)';
        $notificationDateTime = \sprintf('CONCAT(DATE(%s), " ", notifications.send_time, ":00")', $dateTimeHouse);

        $condition = \sprintf('%s = %s', $notificationDateTime, $dateTimeHouse);

        return new Notification()->newQuery()
            ->select('notifications.*', 'users.external_id as user_external_id')
            ->join('houses', 'notifications.house_id', '=', 'houses.id')
            ->join('users', 'notifications.user_id', '=', 'users.id')
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

    public function updateNotification(Notification $notification, UpdateNotification $data): Notification
    {
        $notification->fill($data->toDatabaseArray())->save();

        return $notification;
    }
}
