<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Notification;
use App\Models\NotificationLog;
use Tests\Unit\TestCase;

final class NotificationTest extends TestCase
{
    public function testLogsRelationship(): void
    {
        $this->bootEloquent();

        $relation = new Notification()->logs();

        self::assertInstanceOf(NotificationLog::class, $relation->getRelated());
        self::assertSame('notification_id', $relation->getForeignKeyName());
        self::assertSame('id', $relation->getLocalKeyName());
    }
}
