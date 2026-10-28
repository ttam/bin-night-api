<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api\Houses;

use App\Http\Controllers\Api\Houses\DeleteController;
use App\Models\House;
use App\Repositories\Interfaces\HouseRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class DeleteControllerTest extends TestCase
{
    public function testDeletesHouse(): void
    {
        $house = new House();

        $repository = $this->createMock(HouseRepositoryInterface::class);
        $repository->expects($this->once())->method('deleteHouse')->with($house);

        $response = new DeleteController()($repository, $house);

        self::assertSame(204, $response->getStatusCode());
        self::assertSame('', $response->getContent());
    }
}
