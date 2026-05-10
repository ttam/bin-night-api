<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Clock\Clock;
use App\Services\Notifications\Twilio\Interfaces\TwilioServiceInterface;
use App\Services\Notifications\Twilio\TwilioDumper;
use App\Services\Notifications\Twilio\TwilioService;
use App\Services\Schedule\Interfaces\ScheduleServiceInterface;
use App\Services\Schedule\ScheduleService;
use Illuminate\Support\ServiceProvider;
use Psr\Clock\ClockInterface;

class AppServiceProvider extends ServiceProvider
{
    /** @codeCoverageIgnore */
    public function register(): void
    {
        $isDevelopment = $this->app->environment(['local', 'development', 'testing']);

        $this->app->singleton(ClockInterface::class, Clock::class);
        $this->app->singleton(ScheduleServiceInterface::class, ScheduleService::class);
        $this->app->singleton(TwilioServiceInterface::class, $isDevelopment ? TwilioDumper::class : TwilioService::class);
    }
}
