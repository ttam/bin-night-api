<?php

declare(strict_types=1);

namespace App\Services\Verification\Interfaces;

use App\Enums\VerificationResult;
use App\Services\Verification\Exceptions\VerificationUnavailableException;

/** Proves that someone holds a phone number. The provider keeps the code, its expiry and its guess limit. */
interface PhoneVerifierInterface
{
    /**
     * Send a code to the number (E.164), replacing any it was sent before.
     *
     * @throws VerificationUnavailableException
     */
    public function enrol(string $number): void;

    public function check(string $number, string $code): VerificationResult;
}
