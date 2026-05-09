<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\BinRepository;
use App\Repositories\Interfaces\BinRepositoryInterface;
use App\Repositories\Interfaces\NotificationRepositoryInterface;
use App\Repositories\NotificationRepository;
use Illuminate\Support\ServiceProvider;

final class RepositoryServiceProvider extends ServiceProvider
{
    /** @codeCoverageIgnore */
    public function register(): void
    {
        $this->app->singleton(BinRepositoryInterface::class, BinRepository::class);
        $this->app->singleton(NotificationRepositoryInterface::class, NotificationRepository::class);
    }
}
