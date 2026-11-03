<?php

declare(strict_types=1);

namespace App\Services\Verification;

use App\Enums\VerificationResult;
use App\Services\Verification\Interfaces\PhoneVerifierInterface;

/** For local development and tests: sends nothing and accepts one fixed code. */
final readonly class FakeVerifier implements PhoneVerifierInterface
{
    public const string CODE = '123456';

    public function enrol(string $number): void
    {
    }

    public function check(string $number, string $code): VerificationResult
    {
        return $code === self::CODE ? VerificationResult::APPROVED : VerificationResult::WRONG_CODE;
    }
}
