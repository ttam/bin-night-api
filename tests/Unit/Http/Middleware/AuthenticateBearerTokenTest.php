<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Auth\AuthenticatedUser;
use App\Auth\Authenticators\Interfaces\TokenAuthenticatorInterface;
use App\Auth\Exceptions\AuthenticationFailedException;
use App\Http\Middleware\AuthenticateBearerToken;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateBearerTokenTest extends TestCase
{
    public function testHandleAcceptsValidTokens(): void
    {
        $authenticator = new class() implements TokenAuthenticatorInterface {
            public function authenticate(string $token): AuthenticatedUser
            {
                TestCase::assertSame('valid', $token);

                return new AuthenticatedUser(['sub' => 'example']);
            }
        };

        $request = Request::create('/');
        $request->headers->set('Authorization', 'Bearer valid');

        $response = new AuthenticateBearerToken($authenticator)->handle(
            $request,
            static function (Request $request): Response {
                TestCase::assertSame('example', $request->user()->id);

                return new Response('ok', 200);
            },
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('ok', $response->getContent());
    }

    public function testHandleThrowsExceptionWithInvalidToken(): void
    {
        $authenticator = new class() implements TokenAuthenticatorInterface {
            public function authenticate(string $token): AuthenticatedUser
            {
                throw new AuthenticationFailedException();
            }
        };

        $request = Request::create('/');
        $request->headers->set('Authorization', 'Bearer invalid-token');

        $response = new AuthenticateBearerToken($authenticator)->handle(
            $request,
            static fn (): Response => new Response('', 200),
        );

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('{"message":"Unauthenticated."}', $response->getContent());
    }

    public function testHandleThrowsExceptionWithMissingBearerToken(): void
    {
        $authenticator = new class() implements TokenAuthenticatorInterface {
            public function authenticate(string $token): AuthenticatedUser
            {
                TestCase::fail('Authenticator should not be called.');
            }
        };

        $response = new AuthenticateBearerToken($authenticator)->handle(
            Request::create('/'),
            static fn (): Response => new Response('', 200),
        );

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('{"message":"Unauthenticated."}', $response->getContent());
    }
}
