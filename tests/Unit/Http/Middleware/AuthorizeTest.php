<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\Authorize;
use App\Models\House;
use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class AuthorizeTest extends TestCase
{
    public static function getModelValues(): iterable
    {
        yield 'House' => ['house', House::class];
    }

    #[DataProvider('getModelValues')]
    public function testHandleMapsModels(string $modelName, string $className): void
    {
        $user = new User()->setAttribute('id', '1');

        $gate = $this->createMock(Gate::class);
        $gate->expects($this->once())->method('forUser')->willReturn($gate);
        $gate->expects($this->once())->method('authorize')->with('create', [$className]);

        $repository = $this->createStub(UserRepositoryInterface::class);
        $repository->method('getUserFromExternalId')->willReturn($user);

        $request = Request::create('/')->setUserResolver(static fn (): User => $user);

        $models = [$modelName];
        new Authorize($gate, $repository)->handle(
            $request,
            static fn (): Response => new Response('ok', 200),
            'create',
            ...$models,
        );
    }
}
