<?php

declare(strict_types=1);

namespace App\Auth\Exceptions;

use RuntimeException;

/** The token is for a Clerk account that has been deleted. Its data is kept, but it is no longer someone who can sign in. */
final class AccountDeletedException extends RuntimeException
{
}
