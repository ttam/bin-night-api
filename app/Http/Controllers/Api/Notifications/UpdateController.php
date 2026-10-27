<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Notifications;

use App\DTO\Notification\UpdateNotification;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\UpdateNotificationRequest;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Repositories\Interfaces\NotificationRepositoryInterface;

final class UpdateController extends Controller
{
    public function __invoke(UpdateNotificationRequest $request, NotificationRepositoryInterface $repository, Notification $notification): NotificationResource
    {
        $notification = $repository->updateNotification($notification, UpdateNotification::fromRequest($request));

        return new NotificationResource($notification);
    }
}
