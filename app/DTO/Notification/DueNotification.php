<?php

declare(strict_types=1);

namespace App\DTO\Notification;

use App\Models\Notification;
use DateTimeImmutable;

/** A notification and the one instant, in UTC, that it is to be sent for a particular local date. */
final readonly class DueNotification
{
    public function __construct(public Notification $notification, public DateTimeImmutable $dueAt)
    {
    }
}
