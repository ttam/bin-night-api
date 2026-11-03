<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Verification;

use App\Enums\VerificationResult;
use App\Services\Verification\FakeVerifier;
use PHPUnit\Framework\TestCase;

final class FakeVerifierTest extends TestCase
{
    public function testItAcceptsOnlyTheFixedCode(): void
    {
        $verifier = new FakeVerifier();

        self::assertSame(VerificationResult::APPROVED, $verifier->check('+61400000000', '123456'));
        self::assertSame(VerificationResult::WRONG_CODE, $verifier->check('+61400000000', '654321'));
    }

    public function testEnrolSendsNothing(): void
    {
        new FakeVerifier()->enrol('+61400000000');

        $this->expectNotToPerformAssertions();
    }
}
