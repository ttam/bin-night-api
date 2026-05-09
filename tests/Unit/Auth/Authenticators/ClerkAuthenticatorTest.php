<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Authenticators;

use App\Auth\Authenticators\ClerkAuthenticator;
use App\Auth\Exceptions\AuthenticationFailedException;
use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Tests\Fixtures\Psr\Http\Client\JwksHttpClient;

final class ClerkAuthenticatorTest extends TestCase
{
    private const string JWKS_URL = 'https://example.clerk.accounts.dev/.well-known/jwks.json';

    public function testAuthenticateAcceptsTokenWithoutAuthorizedParty(): void
    {
        $keyPair = self::generateRsaKeyPair();
        $httpClient = new JwksHttpClient(['keys' => [$keyPair['jwk']]]);

        $token = self::makeToken($keyPair['private_key'], [
            'sub' => 'user_123',
        ]);

        $user = new ClerkAuthenticator(
            self::JWKS_URL,
            ['http://localhost:3000'],
            $httpClient,
            new ArrayAdapter(),
        )->authenticate($token);

        self::assertSame('user_123', $user->id);
        self::assertSame('user_123', $user->claims['sub']);
        self::assertArrayNotHasKey('azp', $user->claims);
    }

    public function testAuthenticateAcceptsValidToken(): void
    {
        $keyPair = self::generateRsaKeyPair();
        $httpClient = new JwksHttpClient(['keys' => [$keyPair['jwk']]]);

        $token = self::makeToken($keyPair['private_key'], [
            'sub' => 'user_123',
            'azp' => 'http://localhost:3000',
        ]);

        $user = new ClerkAuthenticator(
            self::JWKS_URL,
            ['http://localhost:3000'],
            $httpClient,
            new ArrayAdapter(),
        )->authenticate($token);

        self::assertSame('user_123', $user->id);
        self::assertSame('user_123', $user->claims['sub']);
        self::assertSame('http://localhost:3000', $user->claims['azp']);
    }

    public function testAuthenticateRejectsExpiredToken(): void
    {
        $this->expectException(AuthenticationFailedException::class);

        $keyPair = self::generateRsaKeyPair();
        $httpClient = new JwksHttpClient(['keys' => [$keyPair['jwk']]]);

        $token = self::makeToken($keyPair['private_key'], [
            'sub' => 'user_123',
            'azp' => 'http://localhost:3000',
            'exp' => \time() - 60,
        ]);

        new ClerkAuthenticator(
            self::JWKS_URL,
            ['http://localhost:3000'],
            $httpClient,
            new ArrayAdapter(),
        )->authenticate($token);
    }

    public function testAuthenticateRejectsInvalidToken(): void
    {
        $this->expectException(AuthenticationFailedException::class);

        new ClerkAuthenticator(
            self::JWKS_URL,
            [],
            new JwksHttpClient([]),
            new ArrayAdapter(),
        )->authenticate('this-is-not-a-jwt');
    }

    public function testAuthenticateRejectsTokenWithInvalidAuthorizedParty(): void
    {
        $this->expectException(AuthenticationFailedException::class);

        $keyPair = self::generateRsaKeyPair();
        $httpClient = new JwksHttpClient(['keys' => [$keyPair['jwk']]]);

        $token = self::makeToken($keyPair['private_key'], [
            'sub' => 'user_123',
            'azp' => 'http://localhost:4000',
        ]);

        new ClerkAuthenticator(
            self::JWKS_URL,
            ['http://localhost:3000'],
            $httpClient,
            new ArrayAdapter(),
        )->authenticate($token);
    }

    public function testAuthenticateRejectsTokenWithNonStringAuthorizedParty(): void
    {
        $this->expectException(AuthenticationFailedException::class);
        $this->expectExceptionMessage('Could not authenticate token.');

        $keyPair = self::generateRsaKeyPair();
        $httpClient = new JwksHttpClient(['keys' => [$keyPair['jwk']]]);

        $token = self::makeToken($keyPair['private_key'], [
            'sub' => 'user_123',
            'azp' => 123,
        ]);

        new ClerkAuthenticator(
            self::JWKS_URL,
            ['http://localhost:3000'],
            $httpClient,
            new ArrayAdapter(),
        )->authenticate($token);
    }

    public function testAuthenticateRejectsTokenWithoutSubject(): void
    {
        $this->expectException(AuthenticationFailedException::class);

        $keyPair = self::generateRsaKeyPair();
        $httpClient = new JwksHttpClient(['keys' => [$keyPair['jwk']]]);

        $token = self::makeToken($keyPair['private_key'], [
            'azp' => 'http://localhost:3000',
        ]);

        new ClerkAuthenticator(
            self::JWKS_URL,
            ['http://localhost:3000'],
            $httpClient,
            new ArrayAdapter(),
        )->authenticate($token);
    }

    private static function base64UrlEncode(string $value): string
    {
        return \rtrim(\strtr(\base64_encode($value), '+/', '-_'), '=');
    }

    private static function generateRsaKeyPair(): array
    {
        $privateKey = \openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);

        \openssl_pkey_export($privateKey, $privateKeyPem);
        $details = \openssl_pkey_get_details($privateKey);

        return [
            'private_key' => $privateKeyPem,
            'jwk' => [
                'kty' => 'RSA',
                'kid' => 'example',
                'use' => 'sig',
                'alg' => 'RS256',
                'n' => self::base64UrlEncode($details['rsa']['n']),
                'e' => self::base64UrlEncode($details['rsa']['e']),
            ],
        ];
    }

    private static function makeToken(string $privateKey, array $claims): string
    {
        $now = \time();

        return JWT::encode(
            \array_merge([
                'iat' => $now,
                'nbf' => $now,
                'exp' => $now + 3600,
            ], $claims),
            $privateKey,
            'RS256',
            'example',
        );
    }
}
