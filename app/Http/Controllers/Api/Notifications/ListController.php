<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Notifications;

use App\Http\Resources\NotificationResource;
use App\Repositories\Interfaces\NotificationRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListController
{
    public function __invoke(Request $request, NotificationRepositoryInterface $repository): AnonymousResourceCollection
    {
        return new AnonymousResourceCollection(
            $repository->getNotificationsForUser($request->user()),
            NotificationResource::class,
        );
    }
}
