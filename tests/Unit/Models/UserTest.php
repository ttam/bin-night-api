<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Bin;
use App\Models\House;
use App\Models\Notification;
use App\Models\User;
use Tests\Unit\TestCase;

final class UserTest extends TestCase
{
    public function testBinsRelationship(): void
    {
        $this->bootEloquent();

        $relation = new User()->bins();

        self::assertInstanceOf(Bin::class, $relation->getRelated());
        self::assertSame('user_id', $relation->getForeignKeyName());
        self::assertSame('id', $relation->getLocalKeyName());
    }

    public function testHousesRelationship(): void
    {
        $this->bootEloquent();

        $relation = new User()->houses();

        self::assertInstanceOf(House::class, $relation->getRelated());
        self::assertSame('user_id', $relation->getForeignKeyName());
    }

    public function testNotificationsRelationship(): void
    {
        $this->bootEloquent();

        $relation = new User()->notifications();

        self::assertInstanceOf(Notification::class, $relation->getRelated());
        self::assertSame('user_id', $relation->getForeignKeyName());
        self::assertSame('id', $relation->getLocalKeyName());
    }
}
