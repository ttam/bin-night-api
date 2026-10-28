<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Plan\Interfaces\AttachActiveEntitiesInterface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class LoadActiveEntities
{
    public function __construct(private AttachActiveEntitiesInterface $attachActiveEntities)
    {
    }

    /** @param \Closure(\Illuminate\Http\Request): \Symfony\Component\HttpFoundation\Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $this->attachActiveEntities->attachToRequest($request);

        return $next($request);
    }
}
