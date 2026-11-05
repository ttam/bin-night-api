<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Auth\AuthenticatedUser;
use App\Auth\Exceptions\AccountDeletedException;
use App\Http\Middleware\ProvisionUser;
use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class ProvisionUserTest extends TestCase
{
    public function testProvisionsTheUserBeforePassingTheRequestOn(): void
    {
        $authenticated = new AuthenticatedUser(['sub' => 'user_111']);
        $request = Request::create('/api/v1/houses')->setUserResolver(static fn (): AuthenticatedUser => $authenticated);
        $provisioned = false;

        $users = $this->createMock(UserRepositoryInterface::class);
        $users->expects($this->once())->method('createUserFromAuthenticatedUser')->with($authenticated)
            ->willReturnCallback(static function () use (&$provisioned): User {
                $provisioned = true;

                return new User();
            });

        $response = new ProvisionUser($users)->handle(
            $request,
            static function () use (&$provisioned): Response {
                return new Response($provisioned ? 'provisioned' : 'not provisioned');
            },
        );

        self::assertSame('provisioned', $response->getContent());
    }

    public function testTheRestOfTheRequestIsHandedTheUserRowSoNobodyHasToLookItUpAgain(): void
    {
        $authenticated = new AuthenticatedUser(['sub' => 'user_111', 'name' => 'Marty McFly']);
        $request = Request::create('/api/v1/houses')->setUserResolver(static fn (): AuthenticatedUser => $authenticated);
        $user = new User();

        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('createUserFromAuthenticatedUser')->willReturn($user);

        $seen = null;
        new ProvisionUser($users)->handle($request, static function (Request $request) use (&$seen): Response {
            $seen = AuthenticatedUser::fromRequest($request);

            return new Response();
        });

        self::assertInstanceOf(AuthenticatedUser::class, $seen);
        self::assertSame($user, $seen->user);
        self::assertSame('user_111', $seen->id);
        self::assertSame('Marty McFly', $seen->name);
    }

    public function testAnAccountThatWasDeletedIsNotAuthenticatedAndTheRequestGoesNoFurther(): void
    {
        $request = Request::create('/api/v1/houses')->setUserResolver(static fn (): AuthenticatedUser => new AuthenticatedUser(['sub' => 'user_111']));

        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('createUserFromAuthenticatedUser')->willThrowException(new AccountDeletedException());

        $response = new ProvisionUser($users)->handle($request, static fn (): Response => throw new \LogicException('The request should not get this far.'));

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('{"message":"Unauthenticated."}', $response->getContent());
    }
}
