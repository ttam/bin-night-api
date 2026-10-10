<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\ActiveEntityRepository;
use App\Repositories\BinRepository;
use App\Repositories\HouseRepository;
use App\Repositories\Interfaces\ActiveEntityRepositoryInterface;
use App\Repositories\Interfaces\BinRepositoryInterface;
use App\Repositories\Interfaces\HouseRepositoryInterface;
use App\Repositories\Interfaces\NotificationRepositoryInterface;
use App\Repositories\Interfaces\PlanRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Repositories\NotificationRepository;
use App\Repositories\PlanRepository;
use App\Repositories\ScheduledBinRepository;
use App\Repositories\UserRepository;
use App\Services\Schedule\Interfaces\ScheduleServiceInterface;
use Illuminate\Support\ServiceProvider;
use Psr\Clock\ClockInterface;

final class RepositoryServiceProvider extends ServiceProvider
{
    /** @codeCoverageIgnore */
    public function register(): void
    {
        $this->app->singleton(BinRepositoryInterface::class, static function ($app): BinRepositoryInterface {
            return new ScheduledBinRepository(
                $app->make(BinRepository::class),
                $app->make(ClockInterface::class),
                $app->make(ScheduleServiceInterface::class),
            );
        });
        $this->app->singleton(ActiveEntityRepositoryInterface::class, ActiveEntityRepository::class);
        $this->app->singleton(HouseRepositoryInterface::class, HouseRepository::class);
        $this->app->singleton(NotificationRepositoryInterface::class, NotificationRepository::class);
        $this->app->singleton(PlanRepositoryInterface::class, PlanRepository::class);
        $this->app->singleton(UserRepositoryInterface::class, UserRepository::class);
    }
}
