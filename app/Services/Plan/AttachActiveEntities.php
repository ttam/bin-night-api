<?php

declare(strict_types=1);

namespace App\Services\Plan;

use App\Auth\AuthenticatedUser;
use App\Repositories\Interfaces\ActiveEntityRepositoryInterface;
use App\Services\Plan\Interfaces\AttachActiveEntitiesInterface;
use Illuminate\Http\Request;

final readonly class AttachActiveEntities implements AttachActiveEntitiesInterface
{
    public function __construct(private ActiveEntityRepositoryInterface $activeEntities)
    {
    }

    public function attachToRequest(Request $request): void
    {
        $request->attributes->set(
            AttachActiveEntitiesInterface::ACTIVE_ENTITIES,
            new ActiveEntitiesLoader(
                fn (): ActiveEntities => $this->activeEntities->getActiveEntitiesForUser(AuthenticatedUser::fromRequest($request)),
            ),
        );
    }
}
