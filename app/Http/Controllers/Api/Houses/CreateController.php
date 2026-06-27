<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Houses;

use App\DTO\House\CreateHouse;
use App\Http\Controllers\Controller;
use App\Http\Requests\House\CreateHouseRequest;
use App\Http\Resources\HouseResource;
use App\Repositories\Interfaces\HouseRepositoryInterface;

final class CreateController extends Controller
{
    public function __invoke(CreateHouseRequest $request, HouseRepositoryInterface $repository): HouseResource
    {
        $house = $repository->createHouse($request->user(), CreateHouse::fromRequest($request));

        return new HouseResource($house);
    }
}
