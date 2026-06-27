<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api\Houses;

use App\Http\Controllers\Api\Houses\UpdateController;
use App\Http\Requests\House\UpdateHouseRequest;
use App\Models\House;
use App\Repositories\Interfaces\HouseRepositoryInterface;
use Illuminate\Contracts\Validation\Validator;
use PHPUnit\Framework\MockObject\Generator\Generator as MockGenerator;
use PHPUnit\Framework\TestCase;

final class UpdateControllerTest extends TestCase
{
    public function testUpdatesHouse(): void
    {
        $validator = new MockGenerator()->testDouble(Validator::class, false);
        $validator->method('validated')->willReturn([]);

        $request = new UpdateHouseRequest()->setValidator($validator);

        $repository = $this->createMock(HouseRepositoryInterface::class);
        $repository->expects($this->once())->method('updateHouse')->willReturn(new House(['name' => 'Updated']));

        $response = new UpdateController()($request, $repository, new House());

        self::assertSame('Updated', $response->name);
    }
}
