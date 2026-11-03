<?php

declare(strict_types=1);

namespace App\Enums;

/** A phone number is `verified` once its owner has entered the code sent to it, and stays verified. */
enum PhoneNumberStatus: string
{
    case PENDING = 'pending';
    case VERIFIED = 'verified';
}
