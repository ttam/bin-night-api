<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Bin;
use App\Models\House;
use Tests\Unit\TestCase;

final class BinTest extends TestCase
{
    public function testHouseRelationship(): void
    {
        $this->bootEloquent();

        $relation = new Bin()->house();

        self::assertInstanceOf(House::class, $relation->getRelated());
        self::assertSame('house_id', $relation->getForeignKeyName());
        self::assertSame('id', $relation->getOwnerKeyName());
    }
}
