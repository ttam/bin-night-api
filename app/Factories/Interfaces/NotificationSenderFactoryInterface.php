<?php

declare(strict_types=1);

namespace App\Factories\Interfaces;

use App\Enums\NotificationType;
use App\Services\Notifications\Interfaces\NotificationSenderInterface;

interface NotificationSenderFactoryInterface
{
    public function create(NotificationType $type): NotificationSenderInterface;
}
