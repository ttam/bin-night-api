<?php

declare(strict_types=1);

namespace App\Factories;

use App\Enums\NotificationType;
use App\Factories\Interfaces\NotificationSenderFactoryInterface;
use App\Services\Notifications;
use App\Services\Notifications\Interfaces\NotificationSenderInterface;
use Illuminate\Contracts\Container\Container;

final readonly class NotificationSenderFactory implements NotificationSenderFactoryInterface
{
    public function __construct(private Container $container)
    {
    }

    public function create(NotificationType $type): NotificationSenderInterface
    {
        return match ($type) {
            NotificationType::SMS => $this->container->make(Notifications\SMSSender::class),
        };
    }
}
