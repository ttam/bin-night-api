<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Bins;

use App\DTO\Bin\UpdateBin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Bin\UpdateBinRequest;
use App\Http\Resources\BinResource;
use App\Models\Bin;
use App\Repositories\Interfaces\BinRepositoryInterface;

final class UpdateController extends Controller
{
    public function __invoke(UpdateBinRequest $request, BinRepositoryInterface $repository, Bin $bin): BinResource
    {
        $bin = $repository->updateBin($bin, UpdateBin::fromRequest($request));

        return new BinResource($bin);
    }
}
