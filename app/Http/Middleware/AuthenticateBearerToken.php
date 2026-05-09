<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Auth\AuthenticatedUser;
use App\Auth\Authenticators\Interfaces\TokenAuthenticatorInterface;
use App\Auth\Exceptions\AuthenticationFailedException;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class AuthenticateBearerToken
{
    public function __construct(private TokenAuthenticatorInterface $authenticator)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
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
