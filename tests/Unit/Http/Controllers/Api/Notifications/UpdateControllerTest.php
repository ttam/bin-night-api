<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api\Notifications;

use App\DTO\Notification\UpdateNotification;
use App\Http\Controllers\Api\Notifications\UpdateController;
use App\Http\Requests\Notification\UpdateNotificationRequest;
use App\Models\Notification;
use App\Repositories\Interfaces\NotificationRepositoryInterface;
use Illuminate\Contracts\Validation\Validator;
use PHPUnit\Framework\MockObject\Generator\Generator as MockGenerator;
use PHPUnit\Framework\TestCase;

final class UpdateControllerTest extends TestCase
{
    public function testUpdatesNotification(): void
    {
        $validator = new MockGenerator()->testDouble(Validator::class, false);
        $validator->method('validated')->willReturn(['target' => '+61400000001']);

        $request = new UpdateNotificationRequest()->setValidator($validator);
        $notification = new Notification();

        $repository = $this->createMock(NotificationRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('updateNotification')
            ->with($notification, $this->callback(
                static fn (UpdateNotification $data): bool => $data->toDatabaseArray() === ['target' => '+61400000001'],
            ))
            ->willReturn(new Notification(['target' => '+61400000001']));

        $response = new UpdateController()($request, $repository, $notification);

        self::assertSame('+61400000001', $response->resource->target);
    }
}
