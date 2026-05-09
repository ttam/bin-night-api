<?php

declare(strict_types=1);

namespace App\Providers;

use App\Auth\Authenticators\ClerkAuthenticator;
use App\Auth\Authenticators\Interfaces\TokenAuthenticatorInterface;
use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

final class AuthServiceProvider extends ServiceProvider
{
    /** @codeCoverageIgnore */
    public function register(): void
    {
        $this->app->singleton(TokenAuthenticatorInterface::class, static function (): TokenAuthenticatorInterface {
            return new ClerkAuthenticator(
                \config('authentication.clerk.jwks_url'),
                \config('authentication.clerk.authorized_parties'),
                new Client(),
                new FilesystemAdapter('clerk_jwks', 3600, \storage_path('framework/cache/clerk-jwks')),
            );
        });
    }
}
