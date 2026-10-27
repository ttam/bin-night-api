<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api\Notifications;

use App\Auth\AuthenticatedUser;
use App\DTO\Notification\CreateNotification;
use App\Enums\NotificationType;
use App\Http\Controllers\Api\Notifications\CreateController;
use App\Http\Requests\Notification\CreateNotificationRequest;
use App\Models\Notification;
use App\Repositories\Interfaces\NotificationRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class CreateControllerTest extends TestCase
{
    public function testCreatesNotification(): void
    {
        $user = new AuthenticatedUser(['sub' => 'user_111']);
        $request = new CreateNotificationRequest([
            'house_id' => '00000000-0000-0000-0000-000000000000',
            'lead_days' => 1,
            'send_time' => '18:00',
            'target' => '+61400000000',
            'type' => 'sms',
        ])->setUserResolver(static fn (): AuthenticatedUser => $user);

        $repository = $this->createMock(NotificationRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('createNotification')
            ->with($user, $this->callback(
                static fn (CreateNotification $data): bool => $data->houseId === '00000000-0000-0000-0000-000000000000'
                    && $data->type === NotificationType::SMS,
            ))
            ->willReturn(new Notification(['target' => '+61400000000']));

        $response = new CreateController()($request, $repository);

        self::assertSame('+61400000000', $response->resource->target);
    }
}
