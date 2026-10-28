<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Bins;

use App\Http\Controllers\Controller;
use App\Models\Bin;
use App\Repositories\Interfaces\BinRepositoryInterface;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class DeleteController extends Controller
{
    public function __invoke(BinRepositoryInterface $repository, Bin $bin): Response
    {
        $repository->deleteBin($bin);

        return new Response(status: SymfonyResponse::HTTP_NO_CONTENT);
    }
}
