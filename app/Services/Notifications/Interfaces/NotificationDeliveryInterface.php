<?php

declare(strict_types=1);

namespace App\Services\Notifications\Interfaces;

use App\Enums\DeliveryResult;
use App\Models\Notification;
use DateTimeImmutable;
use Illuminate\Support\Collection;

interface NotificationDeliveryInterface
{
    /**
     * Sends a notification for a slot, unless that slot has already been sent or is being sent.
     *
     * @param \Illuminate\Support\Collection<int, \App\Models\Bin> $bins
     */
    public function deliver(Notification $notification, Collection $bins, DateTimeImmutable $scheduledFor): DeliveryResult;
}
