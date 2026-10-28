<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Auth\AuthenticatedUser;
use App\Auth\Exceptions\AuthenticationFailedException;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

final class AuthenticatedUserTest extends TestCase
{
    public function testEmptySubThrowsException(): void
    {
        $this->expectException(AuthenticationFailedException::class);
        $this->expectExceptionMessage('Token does not contain a valid subject.');

        new AuthenticatedUser(['sub' => '']);
    }

    public function testFromRequestReturnsTheAuthenticatedUser(): void
    {
        $user = new AuthenticatedUser(['sub' => 'user_111']);
        $request = Request::create('/')->setUserResolver(static fn (): AuthenticatedUser => $user);

        self::assertSame($user, AuthenticatedUser::fromRequest($request));
    }

    public function testMissingSubThrowsException(): void
    {
        $this->expectException(AuthenticationFailedException::class);
        $this->expectExceptionMessage('Token does not contain a valid subject.');

        new AuthenticatedUser(['email' => 'email@example.com']);
    }

    public function testNonStringSubThrowsException(): void
    {
        $this->expectException(AuthenticationFailedException::class);
        $this->expectExceptionMessage('Token does not contain a valid subject.');

        new AuthenticatedUser(['sub' => 123]);
    }
}
