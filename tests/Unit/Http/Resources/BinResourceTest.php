<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\BinResource;
use App\Models\Bin;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

final class BinResourceTest extends TestCase
{
    public function testResource(): void
    {
        $bin = new Bin()
            ->setAttribute('id', 'bin-1')
            ->setAttribute('name', 'Example Bin')
            ->setAttribute('computed_next_bin_night', new \DateTimeImmutable('1985-10-26'));

        self::assertSame([
            'id' => 'bin-1',
            'name' => 'Example Bin',
            'next_bin_night' => '1985-10-26',
        ], new BinResource($bin)->toArray(new Request()));
    }
}
