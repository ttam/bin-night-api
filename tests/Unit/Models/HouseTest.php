<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Bin;
use App\Models\House;
use App\Models\Notification;
use Tests\Unit\TestCase;

final class HouseTest extends TestCase
{
    public function testBinsRelationship(): void
    {
        $this->bootEloquent();

        $relation = new House()->bins();

        self::assertInstanceOf(Bin::class, $relation->getRelated());
        self::assertSame('house_id', $relation->getForeignKeyName());
        self::assertSame('id', $relation->getLocalKeyName());
    }

    public function testNotificationsRelationship(): void
    {
        $this->bootEloquent();

        $relation = new House()->notifications();

        self::assertInstanceOf(Notification::class, $relation->getRelated());
        self::assertSame('bin_id', $relation->getForeignKeyName());
        self::assertSame('id', $relation->getLocalKeyName());
    }
}
