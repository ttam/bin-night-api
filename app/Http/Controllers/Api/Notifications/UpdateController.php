<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Notifications;

use App\Auth\AuthenticatedUser;
use App\DTO\Notification\UpdateNotification;
use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\UpdateNotificationRequest;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Repositories\Interfaces\NotificationRepositoryInterface;
use App\Services\Notifications\Interfaces\TargetResolverInterface;

final class UpdateController extends Controller
{
    public function __invoke(UpdateNotificationRequest $request, NotificationRepositoryInterface $repository, TargetResolverInterface $targets, Notification $notification): NotificationResource
    {
        // A new target is found, or made without sending anything. The notification moves to it at once and waits there, unsent, until it is verified.
        $destination = $request->has('target')
            ? $targets->resolve(
                AuthenticatedUser::fromRequest($request),
                $request->has('type') ? NotificationType::from($request->string('type')->toString()) : $notification->type,
                $request->string('target')->toString(),
            )
            : null;

        return new NotificationResource($repository->updateNotification($notification, UpdateNotification::fromRequest($request, $destination?->uuid)));
    }
}
