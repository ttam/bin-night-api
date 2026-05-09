<?php

declare(strict_types=1);

namespace App\Auth\Authenticators;

use App\Auth\AuthenticatedUser;
use App\Auth\Authenticators\Interfaces\TokenAuthenticatorInterface;
use App\Auth\Exceptions\AuthenticationFailedException;
use Firebase\JWT\CachedKeySet;
use Firebase\JWT\JWT;
use GuzzleHttp\Psr7\HttpFactory;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Client\ClientInterface;
use Throwable;
use UnexpectedValueException;

final readonly class ClerkAuthenticator implements TokenAuthenticatorInterface
{
    public function __construct(
        private string $jwksUrl,
        private array $authorizedParties,
        private ClientInterface $httpClient,
        private CacheItemPoolInterface $cache,
    ) {
    }

    public function authenticate(string $token): AuthenticatedUser
    {
        try {
            $keySet = new CachedKeySet($this->jwksUrl, $this->httpClient, new HttpFactory(), $this->cache, null, true);

            $decoded = JWT::decode($token, $keySet);
            $claims = \json_decode(\json_encode($decoded, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);

            $this->validateAuthorizedParty($claims['azp'] ?? null);

            return new AuthenticatedUser($claims);
        } catch (UnexpectedValueException $exception) {
            throw new AuthenticationFailedException('Invalid token.', previous: $exception);
        } catch (Throwable $exception) {
            throw new AuthenticationFailedException('Could not authenticate token.', previous: $exception);
        }
    }

    private function validateAuthorizedParty(?string $azp): void
    {
        if ($azp === null) {
            return;
        }

        if (\is_string($azp) === false || \in_array($azp, $this->authorizedParties, true) === false) {
            throw new AuthenticationFailedException('Invalid authorized party.');
        }
    }
}
