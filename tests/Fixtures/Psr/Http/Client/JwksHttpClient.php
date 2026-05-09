<?php

declare(strict_types=1);

namespace Tests\Fixtures\Psr\Http\Client;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final readonly class JwksHttpClient implements ClientInterface
{
    private string $jwks;

    public function __construct(array $jwks)
    {
        $this->jwks = \json_encode($jwks, JSON_THROW_ON_ERROR);
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return new Response(200, ['Content-Type' => 'application/json'], $this->jwks);
    }
}
