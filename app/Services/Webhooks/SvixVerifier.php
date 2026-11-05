<?php

declare(strict_types=1);

namespace App\Services\Webhooks;

use App\Services\Webhooks\Interfaces\WebhookVerifierInterface;
use Psr\Clock\ClockInterface;

/**
 * Clerk sends its webhooks through Svix, which signs each one: an HMAC-SHA256 of `<id>.<timestamp>.<body>`, keyed with the
 * endpoint's signing secret (`whsec_` and then base64), sent as `svix-signature: v1,<base64>` (several, space separated,
 * while a secret is being rotated). A timestamp more than five minutes off is refused, so an old message cannot be replayed.
 */
final readonly class SvixVerifier implements WebhookVerifierInterface
{
    private const int TOLERANCE_SECONDS = 300;

    public function __construct(private ?string $secret, private ClockInterface $clock)
    {
    }

    /** @inheritDoc */
    public function verify(string $payload, array $headers): bool
    {
        $key = $this->key();
        $id = $headers['svix-id'] ?? null;
        $timestamp = $headers['svix-timestamp'] ?? null;
        $signatures = $headers['svix-signature'] ?? null;

        // Anything missing means it is not signed, and nothing unsigned is ever accepted, not even with no secret set up.
        if ($key === null || $id === null || $timestamp === null || $signatures === null || \ctype_digit($timestamp) === false) {
            return false;
        }

        if (\abs($this->clock->now()->getTimestamp() - (int) $timestamp) > self::TOLERANCE_SECONDS) {
            return false;
        }

        $expected = \base64_encode(\hash_hmac('sha256', \sprintf('%s.%s.%s', $id, $timestamp, $payload), $key, true));

        foreach (\explode(' ', $signatures) as $candidate) {
            [$version, $signature] = \array_pad(\explode(',', $candidate, 2), 2, '');

            if ($version === 'v1' && \hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    private function key(): ?string
    {
        if ($this->secret === null || $this->secret === '') {
            return null;
        }

        $key = \base64_decode(\str_starts_with($this->secret, 'whsec_') ? \substr($this->secret, 6) : $this->secret, true);

        return $key === false || $key === '' ? null : $key;
    }
}
