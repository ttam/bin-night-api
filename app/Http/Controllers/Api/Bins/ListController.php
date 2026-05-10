<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Bins;

use App\Http\Resources\BinResource;
use App\Repositories\Interfaces\BinRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListController
{
    public function __invoke(Request $request, BinRepositoryInterface $repository): AnonymousResourceCollection
    {
        return new AnonymousResourceCollection(
            $repository->getBinsForUser($request->user()),
            BinResource::class,
        );
    }
}
