<?php

declare(strict_types=1);

namespace App\Providers;

use App\Factories\Interfaces\NotificationSenderFactoryInterface;
use App\Factories\NotificationSenderFactory;
use Illuminate\Support\ServiceProvider;

final class FactoryServiceProvider extends ServiceProvider
{
    /** @codeCoverageIgnore */
    public function register(): void
    {
        $this->app->singleton(NotificationSenderFactoryInterface::class, NotificationSenderFactory::class);
    }
}
