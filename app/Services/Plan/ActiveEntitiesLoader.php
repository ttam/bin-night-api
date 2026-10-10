<?php

declare(strict_types=1);

namespace App\Services\Plan;

use Closure;

final class ActiveEntitiesLoader
{
    private ?ActiveEntities $activeEntities = null;

    /** @param \Closure(): \App\Services\Plan\ActiveEntities $loader */
    public function __construct(private readonly Closure $loader)
    {
    }

    public function get(): ActiveEntities
    {
        return $this->activeEntities ??= ($this->loader)();
    }
}
