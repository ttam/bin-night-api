<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api\Notifications;

use App\Http\Controllers\Api\Notifications\DeleteController;
use App\Models\Notification;
use App\Repositories\Interfaces\NotificationRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class DeleteControllerTest extends TestCase
{
    public function testDeletesNotification(): void
    {
        $notification = new Notification();

        $repository = $this->createMock(NotificationRepositoryInterface::class);
        $repository->expects($this->once())->method('deleteNotification')->with($notification);

        $response = new DeleteController()($repository, $notification);

        self::assertSame(204, $response->getStatusCode());
        self::assertSame('', $response->getContent());
    }
}
