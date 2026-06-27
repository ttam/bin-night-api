<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Houses;

use App\DTO\House\UpdateHouse;
use App\Http\Controllers\Controller;
use App\Http\Requests\House\UpdateHouseRequest;
use App\Http\Resources\HouseResource;
use App\Models\House;
use App\Repositories\Interfaces\HouseRepositoryInterface;

final class UpdateController extends Controller
{
    public function __invoke(UpdateHouseRequest $request, HouseRepositoryInterface $repository, House $house): HouseResource
    {
        $house = $repository->updateHouse($house, UpdateHouse::fromRequest($request));

        return new HouseResource($house);
    }
}
