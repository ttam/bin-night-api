<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api\Notifications;

use App\Auth\AuthenticatedUser;
use App\Http\Controllers\Api\Notifications\ListController;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Repositories\Interfaces\NotificationRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

final class ListControllerTest extends TestCase
{
    public function testReturnsNotificationsForAuthenticatedUser(): void
    {
        $user = new AuthenticatedUser(['sub' => 'example']);
        $request = Request::create('/api/notifications');
        $request->setUserResolver(static fn (): AuthenticatedUser => $user);

        $notifications = new Collection([new Notification()->setAttribute('id', 'notification-1')]);

        $repository = $this->createMock(NotificationRepositoryInterface::class);
        $repository->method('getNotificationsForUser')->with($user)->willReturn($notifications);

        $collection = new ListController()($request, $repository);

        self::assertSame(NotificationResource::class, $collection->collects);
        self::assertSame('notification-1', $collection->resource->first()->getKey());
    }
}
