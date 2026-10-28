<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Notification;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Collection;

final readonly class NotificationEvent
{
    use Dispatchable;

    /** @param \Illuminate\Support\Collection<int, \App\Models\Bin> $bins */
    public function __construct(public Notification $notification, public Collection $bins)
    {
        //
    }
}
