<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Notifications;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Repositories\Interfaces\NotificationRepositoryInterface;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class DeleteController extends Controller
{
    public function __invoke(NotificationRepositoryInterface $repository, Notification $notification): Response
    {
        $repository->deleteNotification($notification);

        return new Response(status: SymfonyResponse::HTTP_NO_CONTENT);
    }
}
