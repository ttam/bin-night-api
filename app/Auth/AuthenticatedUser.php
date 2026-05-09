<?php

declare(strict_types=1);

namespace App\Auth;

use App\Auth\Exceptions\AuthenticationFailedException;

final readonly class AuthenticatedUser
{
    public string $id;

    public function __construct(public array $claims = [])
    {
        $sub = $claims['sub'] ?? null;

        if (\is_string($sub) === false || \trim($sub) === '') {
            throw new AuthenticationFailedException('Token does not contain a valid subject.');
        }

        $this->id = $sub;
    }
}
