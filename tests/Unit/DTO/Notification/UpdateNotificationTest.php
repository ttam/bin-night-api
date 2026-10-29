<?php

declare(strict_types=1);

namespace Tests\Unit\DTO\Notification;

use App\DTO\Notification\UpdateNotification;
use App\Enums\NotificationType;
use App\Http\Requests\Notification\UpdateNotificationRequest;
use Illuminate\Contracts\Validation\Validator;
use PHPUnit\Framework\MockObject\Generator\Generator as MockGenerator;
use PHPUnit\Framework\TestCase;

final class UpdateNotificationTest extends TestCase
{
    public function testFromRequest(): void
    {
        $validator = new MockGenerator()->testDouble(Validator::class, false);
        $validator->method('validated')->willReturn([
            'lead_days' => '2',
            'name' => 'Marty\'s Phone',
            'send_time' => '18:30:00',
            'target' => '+61400000000',
            'type' => NotificationType::SMS->value,
        ]);

        $request = new UpdateNotificationRequest()->setValidator($validator);

        self::assertSame([
            'lead_days' => 2,
            'name' => 'Marty\'s Phone',
            'send_time' => '18:30:00',
            'target' => '+61400000000',
            'type' => 'sms',
        ], UpdateNotification::fromRequest($request)->toDatabaseArray());
    }

    public function testFromRequestOnlyIncludesWhatWasSent(): void
    {
        $validator = new MockGenerator()->testDouble(Validator::class, false);
        $validator->method('validated')->willReturn(['target' => '+61400000000']);

        $request = new UpdateNotificationRequest()->setValidator($validator);

        self::assertSame(['target' => '+61400000000'], UpdateNotification::fromRequest($request)->toDatabaseArray());
    }

    public function testToDatabaseArray(): void
    {
        $array = new UpdateNotification([
            'lead_days' => 2,
            'name' => 'Marty\'s Phone',
            'send_time' => '18:30:00',
            'target' => '+61400000000',
            'type' => NotificationType::SMS,
        ])->toDatabaseArray();

        self::assertSame(2, $array['lead_days']);
        self::assertSame('18:30:00', $array['send_time']);
        self::assertSame('+61400000000', $array['target']);
        self::assertSame('sms', $array['type']);
    }
}
