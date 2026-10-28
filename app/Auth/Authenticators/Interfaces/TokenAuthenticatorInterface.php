<?php

declare(strict_types=1);

namespace App\Auth\Authenticators\Interfaces;

use App\Auth\AuthenticatedUser;

interface TokenAuthenticatorInterface
{
    public function authenticate(string $token): AuthenticatedUser;
}
