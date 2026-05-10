<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\HouseResource;
use App\Models\House;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

final class HouseResourceTest extends TestCase
{
    public function testResource(): void
    {
        $house = new House()
            ->setAttribute('bin_night', 'saturday')
            ->setAttribute('id', 'house-1')
            ->setAttribute('timezone', 'America/Los_Angeles');

        self::assertSame([
            'bin_night' => 'saturday',
            'id' => 'house-1',
            'timezone' => 'America/Los_Angeles',
        ], new HouseResource($house)->toArray(new Request()));
    }
}
