<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Auth\AuthenticatedUser;
use App\Auth\Exceptions\AccountDeletedException;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class ProvisionUser
{
    public function __construct(private UserRepositoryInterface $userRepository)
    {
    }

    /** @param \Closure(\Illuminate\Http\Request): \Symfony\Component\HttpFoundation\Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $authenticated = AuthenticatedUser::fromRequest($request);

        try {
            $user = $this->userRepository->createUserFromAuthenticatedUser($authenticated);
        } catch (AccountDeletedException) {
            return new JsonResponse(['message' => 'Unauthenticated.'], Response::HTTP_UNAUTHORIZED);
        }

        // The row is found once, here; the rest of the request reads it from the authenticated user.
        $withUser = $authenticated->withUser($user);
        $request->setUserResolver(static fn (): AuthenticatedUser => $withUser);

        return $next($request);
    }
}
