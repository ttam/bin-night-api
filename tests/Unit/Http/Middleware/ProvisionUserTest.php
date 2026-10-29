<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Auth\AuthenticatedUser;
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
}
