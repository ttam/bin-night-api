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
        try {
            $this->userRepository->createUserFromAuthenticatedUser(AuthenticatedUser::fromRequest($request));
        } catch (AccountDeletedException) {
            return new JsonResponse(['message' => 'Unauthenticated.'], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
