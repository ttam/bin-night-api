<?php

declare(strict_types=1);

namespace App\Auth;

use App\Auth\Exceptions\AuthenticationFailedException;
use Illuminate\Http\Request;

final readonly class AuthenticatedUser
{
    public string $id;

    /** @param array<mixed> $claims */
    public function __construct(public array $claims = [])
    {
        $sub = $claims['sub'] ?? null;

        if (\is_string($sub) === false || \trim($sub) === '') {
            throw new AuthenticationFailedException('Token does not contain a valid subject.');
        }

        $this->id = $sub;
    }

    /** The user the authentication middleware put on the request. */
    public static function fromRequest(Request $request): self
    {
        // We can't use $request->user() because static analysis types it as an \App\User
        /** @var self $user */
        $user = ($request->getUserResolver())();

        return $user;
    }
}
