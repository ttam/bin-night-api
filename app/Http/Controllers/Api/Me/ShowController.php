<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Me;

use App\Auth\AuthenticatedUser;
use App\Http\Resources\MeResource;
use App\Repositories\Interfaces\PlanRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Services\Plan\PlanSummary;
use Illuminate\Http\Request;

final class ShowController
{
    public function __invoke(Request $request, PlanRepositoryInterface $plans, UserRepositoryInterface $users): MeResource
    {
        $plan = $users->getUserFromExternalId(AuthenticatedUser::fromRequest($request)->id)->plan;

        return new MeResource(new PlanSummary($plan, $plans->getLimits($plan)));
    }
}
