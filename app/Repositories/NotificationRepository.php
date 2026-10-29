<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Auth\AuthenticatedUser;
use App\DTO\Notification\CreateNotification;
use App\DTO\Notification\UpdateNotification;
use App\Enums\DeliveryStatus;
use App\Models\House;
use App\Models\Notification;
use App\Models\User;
use App\Repositories\Interfaces\NotificationRepositoryInterface;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;
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
        // Work out the time in each timezone that houses are in here, rather than asking the database to convert it: a
        // database can only convert named timezones once its timezone tables are loaded, and without them it quietly
        // finds nothing. Grouped by local time, there are only a few cases to ask about.
        $slot = DateTimeImmutable::createFromInterface($dateTime);
        $timezonesByLocalTime = [];

        foreach (House::query()->distinct()->pluck('timezone') as $timezone) {
            /** @var string $timezone */
            $timezonesByLocalTime[$slot->setTimezone(new DateTimeZone($timezone))->format('H:i:s')][] = $timezone;
        }

        if ($timezonesByLocalTime === []) {
            return new Collection();
        }

        return new Notification()->newQuery()
            ->select('notifications.*', 'users.external_id as user_external_id')
            ->join('houses', 'notifications.house_id', '=', 'houses.id')
            ->join('users', 'notifications.user_id', '=', 'users.id')
            ->where(static function (Builder $query) use ($timezonesByLocalTime): void {
                foreach ($timezonesByLocalTime as $localTime => $timezones) {
                    // Send times are HH:MM:SS everywhere: in the API, in the database and in the answer.
                    $query->orWhere(static fn (Builder $query): Builder => $query
                        ->whereIn('houses.timezone', $timezones)
                        ->where('notifications.send_time', '=', $localTime));
                }
            })
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
