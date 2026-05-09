<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\NotificationEvent;
use App\Factories\Interfaces\NotificationSenderFactoryInterface;

final readonly class NotificationListener
{
    public function __construct(private NotificationSenderFactoryInterface $factory)
    {
    }

    public function handle(NotificationEvent $event): void
    {
        $this->factory->create($event->notification->type)->send($event->notification->target, $event->bins);
    }
}
