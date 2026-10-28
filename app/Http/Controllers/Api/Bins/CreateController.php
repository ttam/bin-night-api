<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Bins;

use App\Auth\AuthenticatedUser;
use App\DTO\Bin\CreateBin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Bin\CreateBinRequest;
use App\Http\Resources\BinResource;
use App\Repositories\Interfaces\BinRepositoryInterface;

final class CreateController extends Controller
{
    public function __invoke(CreateBinRequest $request, BinRepositoryInterface $repository): BinResource
    {
        $bin = $repository->createBin(AuthenticatedUser::fromRequest($request), CreateBin::fromRequest($request));

        return new BinResource($bin);
    }
}
