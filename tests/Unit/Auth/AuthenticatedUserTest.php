<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Auth\AuthenticatedUser;
use App\Auth\Exceptions\AuthenticationFailedException;
use PHPUnit\Framework\TestCase;

final class AuthenticatedUserTest extends TestCase
{
    public function testEmptySubThrowsException(): void
    {
        $this->expectException(AuthenticationFailedException::class);
        $this->expectExceptionMessage('Token does not contain a valid subject.');

        new AuthenticatedUser(['sub' => '']);
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
