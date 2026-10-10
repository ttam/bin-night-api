<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources;

use App\Enums\NotificationType;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

final class NotificationResourceTest extends TestCase
{
    public function testResource(): void
    {
        $notification = new Notification()
            ->setAttribute('id', 'notification-1')
            ->setAttribute('lead_days', 2)
            ->setAttribute('send_time', '00:00')
            ->setAttribute('target', '+61400000000')
            ->setAttribute('type', NotificationType::SMS->value);

        self::assertSame([
            'id' => 'notification-1',
            'lead_days' => 2,
            'send_time' => '00:00',
            'target' => '+61400000000',
            'type' => NotificationType::SMS->value,
        ], new NotificationResource($notification)->toArray(new Request()));
    }
}
