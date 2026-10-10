<?php

declare(strict_types=1);

namespace App\Policies;

use App\Repositories\Interfaces\PlanRepositoryInterface;
use App\Repositories\PlanRepository;
use Illuminate\Auth\Access\Response;

abstract readonly class AbstractPolicy
{
    public const string CODE = 'plan-limit-reached';

    public function __construct(protected PlanRepositoryInterface $planRepository = new PlanRepository())
    {
    }

    public static function limitReached(string $message): Response
    {
        return Response::deny($message, self::CODE);
    }
}
