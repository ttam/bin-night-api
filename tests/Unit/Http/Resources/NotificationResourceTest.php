<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources;

use App\Enums\NotificationType;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use PHPUnit\Framework\TestCase;

final class NotificationResourceTest extends TestCase
{
    use ActiveEntitiesRequestHelper;

    public function testResource(): void
    {
        $notification = new Notification()
            ->setAttribute('id', 'notification-1')
            ->setAttribute('lead_days', 2)
            ->setAttribute('name', 'Marty\'s Phone')
            ->setAttribute('send_time', '00:00')
            ->setAttribute('target', '+61400000000')
            ->setAttribute('type', NotificationType::SMS->value);

        $request = $this->createActiveEntitiesRequest(notificationIds: ['notification-1']);

        self::assertSame([
            'active' => true,
            'id' => 'notification-1',
            'lead_days' => 2,
            'name' => 'Marty\'s Phone',
            'send_time' => '00:00',
            'target' => '+61400000000',
            'type' => NotificationType::SMS->value,
        ], new NotificationResource($notification)->toArray($request));
    }

    public function testResourceIsInactiveWhenOverThePlanLimit(): void
    {
        $notification = new Notification()
            ->setAttribute('id', 'notification-1')
            ->setAttribute('lead_days', 2)
            ->setAttribute('name', 'Marty\'s Phone')
            ->setAttribute('send_time', '00:00')
            ->setAttribute('target', '+61400000000')
            ->setAttribute('type', NotificationType::SMS->value);

        $request = $this->createActiveEntitiesRequest();

        self::assertFalse(new NotificationResource($notification)->toArray($request)['active']);
    }
}
