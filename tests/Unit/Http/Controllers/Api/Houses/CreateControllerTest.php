<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api\Houses;

use App\Auth\AuthenticatedUser;
use App\Enums\DayOfWeek;
use App\Http\Controllers\Api\Houses\CreateController;
use App\Http\Requests\House\CreateHouseRequest;
use App\Models\House;
use App\Repositories\Interfaces\HouseRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class CreateControllerTest extends TestCase
{
    public function testCreatesHouse(): void
    {
        $request = new CreateHouseRequest([
            'bin_night' => DayOfWeek::MONDAY->value,
            'name' => 'Example',
            'timezone' => 'UTC',
        ])->setUserResolver(static fn (): AuthenticatedUser => new AuthenticatedUser(['sub' => 'user_111']));

        $repository = $this->createMock(HouseRepositoryInterface::class);
        $repository->expects($this->once())->method('createHouse')->willReturn(new House(['name' => 'Created']));

        $response = new CreateController()($request, $repository);

        self::assertSame('Created', $response->resource->name);
    }
}
