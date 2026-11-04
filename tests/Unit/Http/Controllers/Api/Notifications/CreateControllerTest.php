<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api\Notifications;

use App\Auth\AuthenticatedUser;
use App\DTO\Notification\CreateNotification;
use App\Enums\NotificationType;
use App\Http\Controllers\Api\Notifications\CreateController;
use App\Http\Requests\Notification\CreateNotificationRequest;
use App\Models\Notification;
use App\Models\PhoneNumber;
use App\Repositories\Interfaces\NotificationRepositoryInterface;
use App\Services\Notifications\Interfaces\TargetResolverInterface;
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

        $destination = new PhoneNumber();
        $destination->setAttribute('id', '22222222-2222-4222-8222-222222222222');
        $targets = $this->createMock(TargetResolverInterface::class);
        $targets->expects($this->once())->method('resolve')->with($user, NotificationType::SMS, '+61400000000')->willReturn($destination);

        $repository = $this->createMock(NotificationRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('createNotification')
            ->with($user, $this->callback(
                static fn (CreateNotification $data): bool => $data->houseId === '00000000-0000-0000-0000-000000000000'
                    && $data->phoneNumberId === '22222222-2222-4222-8222-222222222222'
                    && $data->type === NotificationType::SMS,
            ))
            ->willReturn(new Notification()->setRelation('phoneNumber', new PhoneNumber()->setAttribute('number', '+61400000000')));

        $response = new CreateController()($request, $repository, $targets);

        self::assertSame('+61400000000', $response->resource->target);
    }
}
