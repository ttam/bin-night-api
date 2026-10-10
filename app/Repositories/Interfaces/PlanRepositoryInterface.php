<?php

declare(strict_types=1);

namespace App\Repositories\Interfaces;

use App\Enums\Plan;
use App\Services\Plan\PlanLimits;

interface PlanRepositoryInterface
{
    public function getLimits(Plan $plan): PlanLimits;
}
