<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Notifications;

use App\Auth\AuthenticatedUser;
use App\DTO\Notification\CreateNotification;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\CreateNotificationRequest;
use App\Http\Resources\NotificationResource;
use App\Repositories\Interfaces\NotificationRepositoryInterface;

final class CreateController extends Controller
{
    public function __invoke(CreateNotificationRequest $request, NotificationRepositoryInterface $repository): NotificationResource
    {
        $notification = $repository->createNotification(AuthenticatedUser::fromRequest($request), CreateNotification::fromRequest($request));

        return new NotificationResource($notification);
    }
}
