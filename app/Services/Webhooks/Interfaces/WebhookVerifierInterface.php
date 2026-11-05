<?php

declare(strict_types=1);

namespace App\Services\Webhooks\Interfaces;

interface WebhookVerifierInterface
{
    /**
     * Whether a webhook really came from the service that signs it. It is false for anything not properly signed.
     *
     * @param array<string, string|null> $headers the signature headers, by lowercase name
     */
    public function verify(string $payload, array $headers): bool;
}
