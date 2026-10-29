<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\DeliveryStatus;
use App\Models\Notification;
use App\Models\NotificationLog;
use App\Repositories\Interfaces\NotificationLogRepositoryInterface;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;

final readonly class NotificationLogRepository implements NotificationLogRepositoryInterface
{
    public function claim(Notification $notification, DateTimeImmutable $scheduledFor): ?NotificationLog
    {
        $log = new NotificationLog();
        $log->setAttribute('notification_id', $notification->getKey());
        $log->setAttribute('scheduled_for', $scheduledFor);
        $log->setAttribute('status', DeliveryStatus::PROCESSING);

        try {
            $log->save();

            return $log;
        } catch (UniqueConstraintViolationException) {
            // The slot has been claimed before. Only one process can move a failed slot back to processing, because
            // the update only matches while it is still failed.
            $retried = $this->forSlot($notification, $scheduledFor)
                ->where('status', '=', DeliveryStatus::FAILED->value)
                ->update(['status' => DeliveryStatus::PROCESSING->value, 'failure_reason' => null]);

            return $retried === 1 ? $this->forSlot($notification, $scheduledFor)->firstOrFail() : null;
        }
    }

    public function markFailed(NotificationLog $log, string $reason): void
    {
        $log->setAttribute('status', DeliveryStatus::FAILED);
        $log->setAttribute('failure_reason', $reason);
        $log->save();
    }

    public function markSent(NotificationLog $log, DateTimeImmutable $sentAt): void
    {
        $log->setAttribute('status', DeliveryStatus::SENT);
        $log->setAttribute('sent_at', $sentAt);
        $log->save();
    }

    public function statusFor(Notification $notification, DateTimeImmutable $scheduledFor): DeliveryStatus
    {
        return $this->forSlot($notification, $scheduledFor)->firstOrFail()->status;
    }

    /** @return Builder<NotificationLog> */
    private function forSlot(Notification $notification, DateTimeImmutable $scheduledFor): Builder
    {
        return NotificationLog::query()
            ->where('notification_id', '=', $notification->getKey())
            ->where('scheduled_for', '=', $scheduledFor);
    }
}
