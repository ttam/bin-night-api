<?php

declare(strict_types=1);

namespace App\Services\Notifications\Interfaces;

use Illuminate\Support\Collection;

interface NotificationSenderInterface
{
    public function send(string $target, Collection $bins): bool;
}
