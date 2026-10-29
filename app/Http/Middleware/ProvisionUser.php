<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Auth\AuthenticatedUser;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Closure;
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
        $this->userRepository->createUserFromAuthenticatedUser(AuthenticatedUser::fromRequest($request));

        return $next($request);
    }
}
