<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources;

use App\Enums\NotificationType;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Models\PhoneNumber;
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
            ->setAttribute('phone_number_id', 'phone-1')
            ->setAttribute('send_time', '00:00:00')
            ->setRelation('phoneNumber', new PhoneNumber()->setAttribute('number', '+61400000000')->setAttribute('status', 'verified'))
            ->setAttribute('type', NotificationType::SMS->value);

        $request = $this->createActiveEntitiesRequest(notificationIds: ['notification-1']);

        self::assertSame([
            'active' => true,
            'id' => 'notification-1',
            'lead_days' => 2,
            'name' => 'Marty\'s Phone',
            'phone_number_id' => 'phone-1',
            'send_time' => '00:00:00',
            'target' => '+61400000000',
            'type' => NotificationType::SMS->value,
            'verified' => true,
        ], new NotificationResource($notification)->toArray($request));
    }

    public function testResourceIsInactiveWhenOverThePlanLimit(): void
    {
        $notification = new Notification()
            ->setAttribute('id', 'notification-1')
            ->setAttribute('lead_days', 2)
            ->setAttribute('name', 'Marty\'s Phone')
            ->setAttribute('phone_number_id', 'phone-1')
            ->setAttribute('send_time', '00:00:00')
            ->setRelation('phoneNumber', new PhoneNumber()->setAttribute('number', '+61400000000')->setAttribute('status', 'verified'))
            ->setAttribute('type', NotificationType::SMS->value);

        $request = $this->createActiveEntitiesRequest();

        self::assertFalse(new NotificationResource($notification)->toArray($request)['active']);
    }

    public function testResourceIsNotVerifiedWhileItsNumberIsPending(): void
    {
        $notification = new Notification()
            ->setAttribute('id', 'notification-1')
            ->setAttribute('lead_days', 2)
            ->setAttribute('name', 'Marty\'s Phone')
            ->setAttribute('phone_number_id', 'phone-1')
            ->setAttribute('send_time', '00:00:00')
            ->setRelation('phoneNumber', new PhoneNumber()->setAttribute('number', '+61400000000')->setAttribute('status', 'pending'))
            ->setAttribute('type', NotificationType::SMS->value);

        self::assertFalse(new NotificationResource($notification)->toArray($this->createActiveEntitiesRequest(notificationIds: ['notification-1']))['verified']);
    }
}
