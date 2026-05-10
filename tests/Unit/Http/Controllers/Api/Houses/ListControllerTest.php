<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api\Houses;

use App\Auth\AuthenticatedUser;
use App\Http\Controllers\Api\Houses\ListController;
use App\Http\Resources\HouseResource;
use App\Models\House;
use App\Repositories\Interfaces\HouseRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

final class ListControllerTest extends TestCase
{
    public function testReturnsHousesForAuthenticatedUser(): void
    {
        $user = new AuthenticatedUser(['sub' => 'example']);
        $request = Request::create('/api/houses');
        $request->setUserResolver(static fn (): AuthenticatedUser => $user);

        $houses = new Collection([new House()->setAttribute('id', 'house-1')]);

        $repository = $this->createMock(HouseRepositoryInterface::class);
        $repository->method('getHousesForUser')->with($user)->willReturn($houses);

        $collection = new ListController()($request, $repository);

        self::assertSame(HouseResource::class, $collection->collects);
        self::assertSame('house-1', $collection->resource->first()->getKey());
    }
}
