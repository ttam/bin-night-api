<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Notification;
use App\Models\NotificationLog;
use Tests\Unit\TestCase;

final class NotificationLogTest extends TestCase
{
    public function testNotificationRelationship(): void
    {
        $this->bootEloquent();

        $relation = new NotificationLog()->notification();

        self::assertInstanceOf(Notification::class, $relation->getRelated());
        self::assertSame('notification_id', $relation->getForeignKeyName());
        self::assertSame('id', $relation->getOwnerKeyName());
    }
}
