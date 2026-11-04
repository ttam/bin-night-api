<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Auth\AuthenticatedUser;
use App\Enums\NotificationType;
use App\Models\PhoneNumber;
use App\Repositories\Interfaces\PhoneNumberRepositoryInterface;
use App\Services\Notifications\Interfaces\TargetResolverInterface;

final readonly class TargetResolver implements TargetResolverInterface
{
    public function __construct(private PhoneNumberRepositoryInterface $phoneNumbers)
    {
    }

    public function resolve(AuthenticatedUser $user, NotificationType $type, string $target): PhoneNumber
    {
        return match ($type) {
            NotificationType::SMS => $this->phoneNumbers->findOrCreate($user, $target),
        };
    }
}
