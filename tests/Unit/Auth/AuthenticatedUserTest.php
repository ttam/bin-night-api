<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Auth\AuthenticatedUser;
use App\Auth\Exceptions\AuthenticationFailedException;
use App\Models\User;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AuthenticatedUserTest extends TestCase
{
    public static function getClaimsWithoutAValue(): iterable
    {
        yield 'Missing' => [[]];

        yield 'Null' => [['email' => null, 'name' => null]];

        yield 'Empty' => [['email' => '', 'name' => '']];

        yield 'Only spaces' => [['email' => '  ', 'name' => ' ']];

        yield 'Not a string' => [['email' => 123, 'name' => ['Marty']]];
    }

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

    public function testLongProfileClaimsAreCutToTheColumnLength(): void
    {
        $user = new AuthenticatedUser(['sub' => 'user_111', 'email' => \str_repeat('a', 300), 'name' => \str_repeat('é', 300)]);

        self::assertSame(255, \mb_strlen((string) $user->email));
        self::assertSame(255, \mb_strlen((string) $user->name));
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

    public function testProfileClaimsAreReadAndTrimmed(): void
    {
        $user = new AuthenticatedUser(['sub' => 'user_111', 'email' => ' marty@example.com ', 'name' => 'Marty McFly']);

        self::assertSame('marty@example.com', $user->email);
        self::assertSame('Marty McFly', $user->name);
    }

    /** @param array<string, mixed> $claims */
    #[DataProvider('getClaimsWithoutAValue')]
    public function testProfileClaimsWithoutAValueAreNull(array $claims): void
    {
        $user = new AuthenticatedUser(['sub' => 'user_111', ...$claims]);

        self::assertNull($user->email);
        self::assertNull($user->name);
    }

    public function testWithUserIsTheSameTokenWithTheUsersRow(): void
    {
        $authenticated = new AuthenticatedUser(['sub' => 'user_111', 'name' => 'Marty McFly']);
        $user = new User();

        $with = $authenticated->withUser($user);

        self::assertNull($authenticated->user);
        self::assertSame($user, $with->user);
        self::assertSame('user_111', $with->id);
        self::assertSame('Marty McFly', $with->name);
        self::assertSame($authenticated->claims, $with->claims);
    }
}
