<?php

declare(strict_types=1);

use App\Http\Middleware\AuthenticateBearerToken;
use App\Http\Middleware\Authorize;
use App\Http\Middleware\LoadActiveEntities;
use App\Http\Middleware\ProvisionUser;
use App\Policies\AbstractPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: \dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(static function (Middleware $middleware): void {
        $middleware->alias([
            'active-entities' => LoadActiveEntities::class,
            'can' => Authorize::class,
            'jwt.auth' => AuthenticateBearerToken::class,
            'provision-user' => ProvisionUser::class,
        ]);
    })
    ->withSchedule(static function (Schedule $schedule): void {
        $schedule->command('bin-night:trigger-notifications')->everyFifteenMinutes()->withoutOverlapping(14);
        $schedule->command('bin-night:prune-phone-numbers')->daily();
    })
    ->withExceptions(static function (Exceptions $exceptions): void {
        // Give plan-limit denials a stable code so clients can show an upgrade prompt rather than a bare 403.
        // Laravel has already converted the AuthorizationException into this by the time renderers run.
        $exceptions->render(static function (AccessDeniedHttpException $e, Request $request): ?JsonResponse {
            $previous = $e->getPrevious();

            if ($previous instanceof AuthorizationException === false || $previous->response()?->code() !== AbstractPolicy::CODE) {
                return null;
            }

            return new JsonResponse(['code' => AbstractPolicy::CODE, 'message' => $previous->getMessage()], 403);
        });
    })->create();
