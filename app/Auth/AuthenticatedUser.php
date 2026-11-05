<?php

declare(strict_types=1);

namespace App\Auth;

use App\Auth\Exceptions\AuthenticationFailedException;
use App\Models\User;
use Illuminate\Http\Request;

final readonly class AuthenticatedUser
{
    public ?string $email;

    public string $id;

    public ?string $name;

    /**
     * @param array<mixed> $claims
     * @param User|null $user the user's row, once the request has been through provisioning (see `withUser()`)
     */
    public function __construct(public array $claims = [], public ?User $user = null)
    {
        $sub = $claims['sub'] ?? null;

        if (\is_string($sub) === false || \trim($sub) === '') {
            throw new AuthenticationFailedException('Token does not contain a valid subject.');
        }

        $this->id = $sub;
        $this->email = self::cleanClaim($claims['email'] ?? null);
        $this->name = self::cleanClaim($claims['name'] ?? null);
    }

    /** The user the authentication middleware put on the request. */
    public static function fromRequest(Request $request): self
    {
        // We can't use $request->user() because static analysis types it as an \App\User
        /** @var self $user */
        $user = ($request->getUserResolver())();

        return $user;
    }

    /**
     * The same token's user, with their row from the database. Provisioning finds or makes that row once for the request,
     * and everything after it (the policies, the plan, the repositories) reads it from here instead of asking again.
     */
    public function withUser(User $user): self
    {
        return new self($this->claims, $user);
    }

    private static function cleanClaim(mixed $value): ?string
    {
        if (\is_string($value) === false || \trim($value) === '') {
            return null;
        }

        // The columns hold 255 characters.
        return \mb_substr(\trim($value), 0, 255);
    }
}
