<?php

declare(strict_types=1);

namespace App\Repositories\Interfaces;

use App\Auth\AuthenticatedUser;
use App\Services\Plan\ActiveEntities;

interface ActiveEntityRepositoryInterface
{
    public function getActiveEntitiesForUser(AuthenticatedUser $user): ActiveEntities;
}
