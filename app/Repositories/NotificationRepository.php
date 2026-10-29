<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Auth\AuthenticatedUser;
use App\DTO\Notification\CreateNotification;
use App\DTO\Notification\UpdateNotification;
use App\Enums\DeliveryStatus;
use App\Models\Notification;
use App\Models\User;
use App\Repositories\Interfaces\NotificationRepositoryInterface;
use DateTimeInterface;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

final readonly class NotificationRepository implements NotificationRepositoryInterface
{
    public function createNotification(AuthenticatedUser $authenticatedUser, CreateNotification $data): Notification
    {
        $userId = User::query()->where('external_id', $authenticatedUser->id)->value('id');

        $notification = new Notification()->fill([
            'lead_days' => $data->leadDays,
            'name' => $data->name,
            'send_time' => $data->sendTime,
            'target' => $data->target,
            'type' => $data->type->value,
        ]);

        $notification->setAttribute('house_id', $data->houseId);
        $notification->setAttribute('user_id', $userId);
        $notification->save();

        return $notification;
    }

    public function deleteNotification(Notification $notification): void
    {
        $notification->deleteReleasingName();
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
            // A slot that has been sent is finished. One that failed, or is still processing, stays due so that a later
            // run retries it or reports it.
            ->whereNotExists(static fn (QueryBuilder $query): QueryBuilder => $query
                ->selectRaw('1')
                ->from('notification_logs')
                ->whereColumn('notification_logs.notification_id', 'notifications.id')
                ->where('notification_logs.scheduled_for', '=', $dateTime)
                ->where('notification_logs.status', '=', DeliveryStatus::SENT->value))
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
