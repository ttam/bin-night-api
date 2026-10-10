<?php

declare(strict_types=1);

namespace App\Http\Resources\Concerns;

use App\Services\Plan\ActiveEntities;
use App\Services\Plan\ActiveEntitiesLoader;
use App\Services\Plan\Interfaces\AttachActiveEntitiesInterface;
use Illuminate\Http\Request;
use LogicException;

trait HasActiveEntities
{
    private function getActiveEntities(Request $request): ActiveEntities
    {
        $loader = $request->attributes->get(AttachActiveEntitiesInterface::ACTIVE_ENTITIES);

        return $loader instanceof ActiveEntitiesLoader
            ? $loader->get()
            : throw new LogicException('Active entities must be attached to the request by the LoadActiveEntities middleware.');
    }
}
