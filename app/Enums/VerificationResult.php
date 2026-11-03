<?php

declare(strict_types=1);

namespace App\Enums;

/** What a verification provider said about a code. */
enum VerificationResult
{
    case APPROVED;
    case EXPIRED;
    case TOO_MANY_ATTEMPTS;
    case UNAVAILABLE;
    case WRONG_CODE;

    /** A plain message for the person typing the code. */
    public function message(): string
    {
        return match ($this) {
            self::APPROVED => 'That code is right.',
            self::EXPIRED => 'That code has expired. Ask for a new one.',
            self::TOO_MANY_ATTEMPTS => 'Too many wrong codes. Ask for a new one.',
            self::UNAVAILABLE => 'We could not check the code just now. Try again in a moment.',
            self::WRONG_CODE => 'That code is not right. Check it and try again.',
        };
    }
}
