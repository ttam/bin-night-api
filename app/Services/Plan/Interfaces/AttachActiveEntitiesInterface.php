<?php

declare(strict_types=1);

namespace App\Services\Plan\Interfaces;

use Illuminate\Http\Request;

interface AttachActiveEntitiesInterface
{
    public const string ACTIVE_ENTITIES = 'active-entities';

    public function attachToRequest(Request $request): void;
}
