<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Closure;
use Illuminate\Auth\Middleware\Authorize as Middleware;
use Illuminate\Contracts\Auth\Access\Gate;

final class Authorize extends Middleware
{
    private array $classMap = [
        'house' => Models\House::class,
    ];

    public function __construct(Gate $gate, private readonly UserRepositoryInterface $userRepository)
    {
        parent::__construct($gate);
    }

    public function handle($request, Closure $next, $ability, ...$models)
    {
        $user = $this->userRepository->getUserFromExternalId($request->user()->id);
        $this->gate = $this->gate->forUser($user);

        $models[0] = $this->classMap[$models[0]];

        return parent::handle($request, $next, $ability, ...$models);
    }
}
