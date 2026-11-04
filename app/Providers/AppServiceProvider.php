<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Clock\Clock;
use App\Services\Notifications\Interfaces\NotificationDeliveryInterface;
use App\Services\Notifications\Interfaces\TargetResolverInterface;
use App\Services\Notifications\NotificationDelivery;
use App\Services\Notifications\TargetResolver;
use App\Services\Notifications\Twilio\Interfaces\TwilioServiceInterface;
use App\Services\Notifications\Twilio\TwilioDumper;
use App\Services\Notifications\Twilio\TwilioService;
use App\Services\Plan\AttachActiveEntities;
use App\Services\Plan\Interfaces\AttachActiveEntitiesInterface;
use App\Services\Schedule\Interfaces\ScheduleServiceInterface;
use App\Services\Schedule\ScheduleService;
use App\Services\Verification\CodeSender;
use App\Services\Verification\FakeVerifier;
use App\Services\Verification\Interfaces\CodeSenderInterface;
use App\Services\Verification\Interfaces\PhoneVerifierInterface;
use App\Services\Verification\TwilioVerifier;
use Illuminate\Support\ServiceProvider;
use Psr\Clock\ClockInterface;

class AppServiceProvider extends ServiceProvider
{
    /** @codeCoverageIgnore */
    public function register(): void
    {
        $isDevelopment = $this->app->environment(['local', 'development', 'testing']);

        $this->app->singleton(ClockInterface::class, Clock::class);
        $this->app->singleton(AttachActiveEntitiesInterface::class, AttachActiveEntities::class);
        $this->app->singleton(NotificationDeliveryInterface::class, NotificationDelivery::class);
        $this->app->singleton(CodeSenderInterface::class, CodeSender::class);
        $this->app->singleton(PhoneVerifierInterface::class, $isDevelopment ? FakeVerifier::class : TwilioVerifier::class);
        $this->app->singleton(TargetResolverInterface::class, TargetResolver::class);
        $this->app->singleton(ScheduleServiceInterface::class, ScheduleService::class);
        $this->app->singleton(TwilioServiceInterface::class, $isDevelopment ? TwilioDumper::class : TwilioService::class);
    }
}
