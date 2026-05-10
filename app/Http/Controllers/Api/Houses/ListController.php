<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Houses;

use App\Http\Resources\HouseResource;
use App\Repositories\Interfaces\HouseRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListController
{
    public function __invoke(Request $request, HouseRepositoryInterface $repository): AnonymousResourceCollection
    {
        return new AnonymousResourceCollection(
            $repository->getHousesForUser($request->user()),
            HouseResource::class,
        );
    }
}
