<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Auth\AuthenticatedUser;
use App\Auth\Authenticators\Interfaces\TokenAuthenticatorInterface;
use App\Auth\Exceptions\AuthenticationFailedException;
use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class AuthenticateBearerToken
{
    private const array DEVELOPMENT_ENVIRONMENTS = ['development', 'local', 'testing'];

    public function __construct(private Repository $config, private TokenAuthenticatorInterface $authenticator)
    {
    }

    /** @param \Closure(\Illuminate\Http\Request): \Symfony\Component\HttpFoundation\Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $developmentUser = $this->config->get('authentication.clerk.development_user');
        $isDevelopment = \in_array($this->config->get('app.env'), self::DEVELOPMENT_ENVIRONMENTS, true);

        if ($isDevelopment && \is_string($developmentUser) && \trim($developmentUser) !== '') {
            $request->setUserResolver(
                static fn (): AuthenticatedUser => new AuthenticatedUser(['sub' => $developmentUser])
            );

            return $next($request);
        }

        $token = $request->bearerToken();

        if (\is_string($token) === false || \trim($token) === '') {
            return $this->unauthenticated();
        }

        try {
            $user = $this->authenticator->authenticate($token);
        } catch (AuthenticationFailedException) {
            return $this->unauthenticated();
        }

        $request->setUserResolver(static fn (): AuthenticatedUser => $user);

        return $next($request);
    }

    private function unauthenticated(): JsonResponse
    {
        return new JsonResponse(['message' => 'Unauthenticated.'], Response::HTTP_UNAUTHORIZED);
    }
}
