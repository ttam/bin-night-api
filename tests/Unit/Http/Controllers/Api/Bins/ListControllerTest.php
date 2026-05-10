<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api\Bins;

use App\Auth\AuthenticatedUser;
use App\Http\Controllers\Api\Bins\ListController;
use App\Http\Resources\BinResource;
use App\Models\Bin;
use App\Repositories\Interfaces\BinRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

final class ListControllerTest extends TestCase
{
    public function testReturnsBinsForAuthenticatedUser(): void
    {
        $user = new AuthenticatedUser(['sub' => 'example']);
        $request = Request::create('/api/bins');
        $request->setUserResolver(static fn (): AuthenticatedUser => $user);

        $bins = new Collection([new Bin()->setAttribute('id', 'bin-1')]);

        $repository = $this->createMock(BinRepositoryInterface::class);
        $repository->method('getBinsForUser')->with($user)->willReturn($bins);

        $collection = new ListController()($request, $repository);

        self::assertSame(BinResource::class, $collection->collects);
        self::assertSame('bin-1', $collection->resource->first()->getKey());
    }
}
