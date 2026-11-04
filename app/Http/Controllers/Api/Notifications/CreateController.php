<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Notifications;

use App\Auth\AuthenticatedUser;
use App\DTO\Notification\CreateNotification;
use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\CreateNotificationRequest;
use App\Http\Resources\NotificationResource;
use App\Repositories\Interfaces\NotificationRepositoryInterface;
use App\Services\Notifications\Interfaces\TargetResolverInterface;

final class CreateController extends Controller
{
    public function __invoke(CreateNotificationRequest $request, NotificationRepositoryInterface $repository, TargetResolverInterface $targets): NotificationResource
    {
        $user = AuthenticatedUser::fromRequest($request);
        $destination = $targets->resolve($user, NotificationType::from($request->string('type')->toString()), $request->string('target')->toString());

        return new NotificationResource($repository->createNotification($user, CreateNotification::fromRequest($request, $destination->uuid)));
    }
}
