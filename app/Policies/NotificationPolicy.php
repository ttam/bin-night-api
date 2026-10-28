<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\NotificationType;
use App\Models\House;
use App\Models\Notification;
use App\Models\User;
use App\Services\Plan\PlanLimits;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Str;

final readonly class NotificationPolicy extends AbstractPolicy
{
    /**
     * $house is null if an invalid one is passed in the request.
     * not found and not allowed are treated the same, but we're
     * not giving anything away to the user.
     *
     * $type is the raw request input. If it is not a known type we let the request through, so that validation can
     * reject it with a proper error.
     */
    public function create(User $user, ?House $house, mixed $type = null): Response
    {
        if ($house === null || $house->userId !== $user->getKey()) {
            return Response::denyAsNotFound();
        }

        $limits = $this->planRepository->getLimits($user->plan);

        $notificationType = \is_string($type) ? NotificationType::tryFrom($type) : null;

        if ($notificationType !== null && $limits->allowsNotificationType($notificationType) === false) {
            return self::typeNotIncluded($notificationType);
        }

        // Notifications of a type the plan does not allow are inactive and do not use a slot, so they are not counted.
        $existing = Notification::query()
            ->where('house_id', '=', $house->getKey())
            ->whereIn('type', \array_map(static fn (NotificationType $type): string => $type->value, $limits->notificationTypes))
            ->count();

        return ($limits->notificationsPerHouse === PlanLimits::UNLIMITED || $existing < $limits->notificationsPerHouse)
            ? Response::allow()
            : self::limitReached(\sprintf(
                'Your plan allows %d %s per house.',
                $limits->notificationsPerHouse,
                Str::plural('notification', $limits->notificationsPerHouse),
            ));
    }

    /**
     * Only a change of type is checked against the plan, so a notification that is already over the plan can still be
     * edited. $type is the raw request input; an unknown type is let through for validation to report.
     */
    public function update(User $user, Notification $notification, mixed $type = null): Response
    {
        if ($notification->userId !== $user->getKey()) {
            return Response::denyAsNotFound();
        }

        $newType = \is_string($type) ? NotificationType::tryFrom($type) : null;

        if ($newType === null || $newType->value === $notification->getAttribute('type')) {
            return Response::allow();
        }

        return $this->planRepository->getLimits($user->plan)->allowsNotificationType($newType)
            ? Response::allow()
            : self::typeNotIncluded($newType);
    }

    private static function typeNotIncluded(NotificationType $type): Response
    {
        return self::limitReached(\sprintf('Your plan does not include %s notifications.', $type->value));
    }
}
