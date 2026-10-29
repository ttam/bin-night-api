<?php

declare(strict_types=1);

namespace App\Repositories\Interfaces;

use App\Enums\DeliveryStatus;
use App\Models\Notification;
use App\Models\NotificationLog;
use DateTimeImmutable;

interface NotificationLogRepositoryInterface
{
    /**
     * Takes the right to deliver a notification for a slot, or returns null if it is not ours to take. A slot is only
     * ever claimed once, except that a failed one can be claimed again.
     */
    public function claim(Notification $notification, DateTimeImmutable $scheduledFor): ?NotificationLog;

    public function markFailed(NotificationLog $log, string $reason): void;

    public function markSent(NotificationLog $log, DateTimeImmutable $sentAt): void;

    /** The status of a slot that exists, which is how the caller of a failed claim finds out why it failed. */
    public function statusFor(Notification $notification, DateTimeImmutable $scheduledFor): DeliveryStatus;
}
