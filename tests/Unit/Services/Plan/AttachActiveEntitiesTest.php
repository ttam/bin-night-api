<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Plan;

use App\Auth\AuthenticatedUser;
use App\Repositories\Interfaces\ActiveEntityRepositoryInterface;
use App\Services\Plan\ActiveEntities;
use App\Services\Plan\ActiveEntitiesLoader;
use App\Services\Plan\AttachActiveEntities;
use App\Services\Plan\Interfaces\AttachActiveEntitiesInterface;
use App\Services\Plan\PlanLimits;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

final class AttachActiveEntitiesTest extends TestCase
{
    public function testAttachesALoaderThatDoesNotQueryUntilItIsRead(): void
    {
        $repository = $this->createMock(ActiveEntityRepositoryInterface::class);
        $repository->expects($this->never())->method('getActiveEntitiesForUser');

        $request = Request::create('/api/bins');

        new AttachActiveEntities($repository)->attachToRequest($request);

        self::assertInstanceOf(
            ActiveEntitiesLoader::class,
            $request->attributes->get(AttachActiveEntitiesInterface::ACTIVE_ENTITIES),
        );
    }

    public function testTheLoaderGetsTheAuthenticatedUsersActiveEntities(): void
    {
        $activeEntities = new ActiveEntities(new PlanLimits(1, 2, 2, []), [], [], []);

        $user = new AuthenticatedUser(['sub' => 'user_111']);

        $repository = $this->createMock(ActiveEntityRepositoryInterface::class);
        $repository->expects($this->once())->method('getActiveEntitiesForUser')->with($user)->willReturn($activeEntities);

        $request = Request::create('/api/bins');
        $request->setUserResolver(static fn (): AuthenticatedUser => $user);

        new AttachActiveEntities($repository)->attachToRequest($request);

        self::assertSame(
            $activeEntities,
            $request->attributes->get(AttachActiveEntitiesInterface::ACTIVE_ENTITIES)->get(),
        );
    }
}
