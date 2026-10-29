<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\BinResource;
use App\Models\Bin;
use App\Models\House;
use PHPUnit\Framework\TestCase;

final class BinResourceTest extends TestCase
{
    use ActiveEntitiesRequestHelper;

    public function testResource(): void
    {
        $bin = new Bin()
            ->setAttribute('bin_color', '000000')
            ->setAttribute('computed_next_bin_night', new \DateTimeImmutable('1985-10-26', new \DateTimeZone('UTC')))
            ->setAttribute('cycle', 'weekly')
            ->setAttribute('id', 'bin-1')
            ->setAttribute('lid_color', 'ffffff')
            ->setAttribute('name', 'Example Bin')
            ->setAttribute('start_date', new \DateTimeImmutable('1985-10-26'))
            ->setRelation('house', new House()->setAttribute('timezone', 'UTC'));

        $request = $this->createActiveEntitiesRequest(binIds: ['bin-1']);

        self::assertSame([
            'active' => true,
            'bin_color' => '#000000',
            'cycle' => 'weekly',
            'id' => 'bin-1',
            'lid_color' => '#ffffff',
            'name' => 'Example Bin',
            'next_bin_night' => '1985-10-26T00:00:00+00:00',
            'start_date' => '1985-10-26',
        ], new BinResource($bin)->toArray($request));
    }

    public function testResourceIsInactiveWhenOverThePlanLimit(): void
    {
        $bin = new Bin()
            ->setAttribute('bin_color', '000000')
            ->setAttribute('computed_next_bin_night', new \DateTimeImmutable('1985-10-26', new \DateTimeZone('UTC')))
            ->setAttribute('cycle', 'weekly')
            ->setAttribute('id', 'bin-1')
            ->setAttribute('lid_color', 'ffffff')
            ->setAttribute('name', 'Example Bin')
            ->setAttribute('start_date', new \DateTimeImmutable('1985-10-26'))
            ->setRelation('house', new House()->setAttribute('timezone', 'UTC'));

        $request = $this->createActiveEntitiesRequest();

        $array = new BinResource($bin)->toArray($request);

        self::assertFalse($array['active']);
        self::assertNull($array['next_bin_night']);
    }
}
