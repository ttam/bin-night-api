<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Houses;

use App\Http\Controllers\Controller;
use App\Models\House;
use App\Repositories\Interfaces\HouseRepositoryInterface;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class DeleteController extends Controller
{
    public function __invoke(HouseRepositoryInterface $repository, House $house): Response
    {
        $repository->deleteHouse($house);

        return new Response(status: SymfonyResponse::HTTP_NO_CONTENT);
    }
}
