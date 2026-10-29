<?php

declare(strict_types=1);

namespace App\Services\Notifications\Interfaces;

use Illuminate\Support\Collection;

interface NotificationSenderInterface
{
    /**
     * @param \Illuminate\Support\Collection<int, \App\Models\Bin> $bins
     *
     * @throws \App\Services\Notifications\Exceptions\DeliveryFailedException
     */
    public function send(string $target, Collection $bins): void;
}
