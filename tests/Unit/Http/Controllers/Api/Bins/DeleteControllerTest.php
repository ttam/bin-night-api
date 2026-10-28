<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api\Bins;

use App\Http\Controllers\Api\Bins\DeleteController;
use App\Models\Bin;
use App\Repositories\Interfaces\BinRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class DeleteControllerTest extends TestCase
{
    public function testDeletesBin(): void
    {
        $bin = new Bin();

        $repository = $this->createMock(BinRepositoryInterface::class);
        $repository->expects($this->once())->method('deleteBin')->with($bin);

        $response = new DeleteController()($repository, $bin);

        self::assertSame(204, $response->getStatusCode());
        self::assertSame('', $response->getContent());
    }
}
