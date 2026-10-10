<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\HouseResource;
use App\Models\House;
use Illuminate\Http\Request;
use LogicException;
use PHPUnit\Framework\TestCase;

final class HouseResourceTest extends TestCase
{
    use ActiveEntitiesRequestHelper;

    public function testResource(): void
    {
        $house = new House()
            ->setAttribute('bin_night', 'saturday')
            ->setAttribute('id', 'house-1')
            ->setAttribute('name', 'Example')
            ->setAttribute('timezone', 'America/Los_Angeles');

        $request = $this->createActiveEntitiesRequest(houseIds: ['house-1']);

        self::assertSame([
            'active' => true,
            'bin_night' => 'saturday',
            'id' => 'house-1',
            'name' => 'Example',
            'timezone' => 'America/Los_Angeles',
        ], new HouseResource($house)->toArray($request));
    }

    public function testResourceIsInactiveWhenOverThePlanLimit(): void
    {
        $house = new House()
            ->setAttribute('bin_night', 'saturday')
            ->setAttribute('id', 'house-1')
            ->setAttribute('name', 'Example')
            ->setAttribute('timezone', 'America/Los_Angeles');

        $request = $this->createActiveEntitiesRequest();

        self::assertFalse(new HouseResource($house)->toArray($request)['active']);
    }

    public function testThrowsWhenActiveEntitiesWereNotAttachedToTheRequest(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Active entities must be attached to the request by the LoadActiveEntities middleware.');

        new HouseResource(new House())->toArray(new Request());
    }
}
