<?php

declare(strict_types=1);

namespace App\Services\Plan;

use App\Enums\Plan;

/** A user's plan and what it allows. */
final readonly class PlanSummary
{
    public function __construct(public Plan $plan, public PlanLimits $limits)
    {
    }
}
