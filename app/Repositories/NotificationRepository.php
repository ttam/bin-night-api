<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Auth\AuthenticatedUser;
use App\DTO\Notification\CreateNotification;
use App\DTO\Notification\DueNotification;
use App\DTO\Notification\UpdateNotification;
use App\Enums\DeliveryStatus;
use App\Models\Notification;
use App\Models\NotificationLog;
use App\Models\User;
use App\Repositories\Interfaces\NotificationRepositoryInterface;
use App\Services\Schedule\Calendar;
use DateTimeImmutable;
use DateTimeZone;
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
    public function getDueNotifications(DateTimeImmutable $after, DateTimeImmutable $until): Collection
    {
        $utc = new DateTimeZone('UTC');
        $after = $after->setTimezone($utc);
        $until = $until->setTimezone($utc);

        // The send instant is worked out here, in PHP, for each notification and each local date the window touches.
        // A database cannot do it: it would need its timezone tables loaded, and on a clock-change night a local time
        // has two instants or none. There are few notifications per user, so reading them all is cheap.
        $notifications = new Notification()->newQuery()
            ->select('notifications.*', 'users.external_id as user_external_id', 'houses.timezone as house_timezone')
            ->join('houses', 'notifications.house_id', '=', 'houses.id')
            ->join('users', 'notifications.user_id', '=', 'users.id')
            ->get();

        $sent = $this->sentBetween($after, $until);
        $timezones = [];
        $due = [];

        foreach ($notifications as $notification) {
            /** @var string $timezoneName */
            $timezoneName = $notification->getAttribute('house_timezone');
            /** @var string $sendTime */
            $sendTime = $notification->getAttribute('send_time');

            $timezone = $timezones[$timezoneName] ??= new DateTimeZone($timezoneName);

            $day = $after->setTimezone($timezone)->setTime(0, 0);
            $lastDay = $until->setTimezone($timezone)->format('Y-m-d');

            for (; $day->format('Y-m-d') <= $lastDay; $day = $day->modify('+1 day')) {
                $dueAt = Calendar::sendInstant($day->format('Y-m-d'), $sendTime, $timezone);

                if ($dueAt > $after && $dueAt <= $until && isset($sent[$this->key($notification, $dueAt)]) === false) {
                    $due[] = new DueNotification($notification, $dueAt);
                }
            }
        }

        \usort($due, fn (DueNotification $a, DueNotification $b): int => [$a->dueAt, $this->key($a->notification, $a->dueAt)] <=> [$b->dueAt, $this->key($b->notification, $b->dueAt)]);

        return new Collection($due);
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

    private function key(Notification $notification, DateTimeImmutable $dueAt): string
    {
        /** @var string $id */
        $id = $notification->getKey();

        return \sprintf(
            '%s|%s',
            $id,
            $dueAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        );
    }

    /**
     * A slot that has been sent is finished. One that failed, or is still processing, stays due so that a later run
     * retries it or reports it.
     *
     * @return array<string, true>
     */
    private function sentBetween(DateTimeImmutable $after, DateTimeImmutable $until): array
    {
        $sent = [];

        $rows = NotificationLog::query()->toBase()
            ->where('status', '=', DeliveryStatus::SENT->value)
            ->where('scheduled_for', '>', $after)
            ->where('scheduled_for', '<=', $until)
            ->get(['notification_id', 'scheduled_for']);

        foreach ($rows as $row) {
            /** @var object{notification_id: string, scheduled_for: string} $row */
            $sent[$row->notification_id . '|' . $row->scheduled_for] = true;
        }

        return $sent;
    }
}
