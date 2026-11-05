<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Webhooks;

use App\Services\Clock\KnownClock;
use App\Services\Webhooks\SvixVerifier;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SvixVerifierTest extends TestCase
{
    private const string BODY = '{"type":"user.deleted","data":{"id":"user_111"}}';

    private const string KEY = 'a-very-secret-signing-key-12345';

    private const int NOW = 499_132_800;

    public static function sign(string $body, string $id, int $timestamp, string $key = self::KEY): string
    {
        return 'v1,' . \base64_encode(\hash_hmac('sha256', \sprintf('%s.%d.%s', $id, $timestamp, $body), $key, true));
    }

    public function testAProperlySignedMessageIsAccepted(): void
    {
        self::assertTrue($this->verifier()->verify(self::BODY, $this->headers()));
    }

    public function testTheSecretMayBeGivenWithoutItsPrefix(): void
    {
        self::assertTrue($this->verifier(\base64_encode(self::KEY))->verify(self::BODY, $this->headers()));
    }

    public function testAMessageSignedWithAnotherSecretIsRefused(): void
    {
        self::assertFalse($this->verifier()->verify(self::BODY, $this->headers(signature: self::sign(self::BODY, 'msg_1', self::NOW, 'another-key'))));
    }

    public function testAChangedBodyIsRefused(): void
    {
        self::assertFalse($this->verifier()->verify('{"type":"user.deleted","data":{"id":"user_222"}}', $this->headers()));
    }

    public function testAChangedIdOrTimestampIsRefused(): void
    {
        self::assertFalse($this->verifier()->verify(self::BODY, $this->headers(id: 'msg_2')));
        self::assertFalse($this->verifier()->verify(self::BODY, $this->headers(timestamp: (string) (self::NOW + 1))));
    }

    /** @return iterable<string, array{int}> */
    public static function offsets(): iterable
    {
        yield 'five minutes old is still fine' => [-300];
        yield 'five minutes ahead is still fine' => [300];
    }

    #[DataProvider('offsets')]
    public function testTheTimestampMayBeUpToFiveMinutesOffTheClock(int $offset): void
    {
        $timestamp = self::NOW + $offset;

        self::assertTrue($this->verifier()->verify(self::BODY, $this->headers(timestamp: (string) $timestamp, signature: self::sign(self::BODY, 'msg_1', $timestamp))));
    }

    /** @return iterable<string, array{int}> */
    public static function tooFarOff(): iterable
    {
        yield 'a replay from an hour ago' => [-3600];
        yield 'a second over five minutes old' => [-301];
        yield 'a second over five minutes ahead' => [301];
    }

    #[DataProvider('tooFarOff')]
    public function testAnOldOrFutureMessageIsRefusedEvenIfProperlySigned(int $offset): void
    {
        $timestamp = self::NOW + $offset;

        self::assertFalse($this->verifier()->verify(self::BODY, $this->headers(timestamp: (string) $timestamp, signature: self::sign(self::BODY, 'msg_1', $timestamp))));
    }

    public function testOneGoodSignatureAmongSeveralIsEnough(): void
    {
        $good = self::sign(self::BODY, 'msg_1', self::NOW);
        $old = self::sign(self::BODY, 'msg_1', self::NOW, 'the-old-key');

        self::assertTrue($this->verifier()->verify(self::BODY, $this->headers(signature: "{$old} {$good}")));
        self::assertTrue($this->verifier()->verify(self::BODY, $this->headers(signature: "{$good} {$old}")));
    }

    public function testOnlyVersionOneSignaturesCount(): void
    {
        $good = self::sign(self::BODY, 'msg_1', self::NOW);

        self::assertFalse($this->verifier()->verify(self::BODY, $this->headers(signature: \str_replace('v1,', 'v2,', $good))));
        self::assertFalse($this->verifier()->verify(self::BODY, $this->headers(signature: \substr($good, 3))));
        self::assertFalse($this->verifier()->verify(self::BODY, $this->headers(signature: 'v1')));
    }

    /** @return iterable<string, array{string}> */
    public static function missingHeaders(): iterable
    {
        yield 'no id' => ['svix-id'];
        yield 'no timestamp' => ['svix-timestamp'];
        yield 'no signature' => ['svix-signature'];
    }

    #[DataProvider('missingHeaders')]
    public function testAMessageMissingAHeaderIsRefused(string $missing): void
    {
        $headers = $this->headers();
        unset($headers[$missing]);

        self::assertFalse($this->verifier()->verify(self::BODY, $headers));
    }

    public function testATimestampThatIsNotAWholeNumberIsRefused(): void
    {
        foreach (['', 'now', '1.5', '-1', '49913280a'] as $timestamp) {
            self::assertFalse($this->verifier()->verify(self::BODY, $this->headers(timestamp: $timestamp)), $timestamp);
        }
    }

    /** @return iterable<string, array{string|null}> */
    public static function unusableSecrets(): iterable
    {
        yield 'not set' => [null];
        yield 'empty' => [''];
        yield 'only the prefix' => ['whsec_'];
        yield 'not base64' => ['whsec_%%%not base64%%%'];
    }

    #[DataProvider('unusableSecrets')]
    public function testNothingIsAcceptedWithoutAUsableSecret(?string $secret): void
    {
        self::assertFalse($this->verifier($secret)->verify(self::BODY, $this->headers()));
        // Not even a message signed with an empty key.
        self::assertFalse($this->verifier($secret)->verify(self::BODY, $this->headers(signature: self::sign(self::BODY, 'msg_1', self::NOW, ''))));
    }

    /**
     * The headers of a message that was signed for message `msg_1` at the clock's time. Changing the id or the timestamp
     * without a matching signature is what makes it a different, unsigned message.
     *
     * @return array<string, string|null>
     */
    private function headers(?string $id = null, ?string $timestamp = null, ?string $signature = null): array
    {
        return [
            'svix-id' => $id ?? 'msg_1',
            'svix-signature' => $signature ?? self::sign(self::BODY, 'msg_1', self::NOW),
            'svix-timestamp' => $timestamp ?? (string) self::NOW,
        ];
    }

    private function verifier(?string $secret = null): SvixVerifier
    {
        return new SvixVerifier(
            \func_num_args() === 0 ? 'whsec_' . \base64_encode(self::KEY) : $secret,
            new KnownClock(new DateTimeImmutable('@' . self::NOW, new DateTimeZone('UTC'))),
        );
    }
}
