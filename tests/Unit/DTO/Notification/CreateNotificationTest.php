<?php

declare(strict_types=1);

namespace Tests\Unit\DTO\Notification;

use App\DTO\Notification\CreateNotification;
use App\Enums\NotificationType;
use App\Http\Requests\Notification\CreateNotificationRequest;
use PHPUnit\Framework\TestCase;

final class CreateNotificationTest extends TestCase
{
    public function testFromRequest(): void
    {
        $dto = CreateNotification::fromRequest(new CreateNotificationRequest([
            'house_id' => '00000000-0000-0000-0000-000000000000',
            'lead_days' => '2',
            'name' => 'Marty\'s Phone',
            'send_time' => '18:30:00',
            'target' => '+61400000000',
            'type' => 'sms',
        ]));

        self::assertSame('00000000-0000-0000-0000-000000000000', $dto->houseId);
        self::assertSame(2, $dto->leadDays);
        self::assertSame('Marty\'s Phone', $dto->name);
        self::assertSame('18:30:00', $dto->sendTime);
        self::assertSame('+61400000000', $dto->target);
        self::assertSame(NotificationType::SMS, $dto->type);
    }
}
