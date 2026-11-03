<?php

declare(strict_types=1);

namespace App\Services\Verification\Exceptions;

use RuntimeException;

/** The provider could not send a code. The message never holds the phone number. */
final class VerificationUnavailableException extends RuntimeException
{
}
