<?php

declare(strict_types=1);

namespace App\Services\Notifications\Exceptions;

use RuntimeException;

/** The message is a short reason that is safe to store and log: no phone numbers or message text. */
final class DeliveryFailedException extends RuntimeException
{
}
