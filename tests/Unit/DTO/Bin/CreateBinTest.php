<?php

declare(strict_types=1);

namespace Tests\Unit\DTO\Bin;

use App\DTO\Bin\CreateBin;
use App\Enums\Cycle;
use App\Http\Requests\Bin\CreateBinRequest;
use PHPUnit\Framework\TestCase;

final class CreateBinTest extends TestCase
{
    public function testFromRequest(): void
    {
        $dto = CreateBin::fromRequest(new CreateBinRequest([
            'bin_color' => '#00dd00',
            'cycle' => 'fortnightly',
            'house_id' => '00000000-0000-0000-0000-000000000000',
            'lid_color' => '#ffffff',
            'name' => 'Example',
            'start_date' => '1985-11-06',
        ]));

        self::assertSame('00dd00', $dto->binColor);
        self::assertSame(Cycle::FORTNIGHTLY, $dto->cycle);
        self::assertSame('00000000-0000-0000-0000-000000000000', $dto->houseId);
        self::assertSame('ffffff', $dto->lidColor);
        self::assertSame('Example', $dto->name);
        self::assertSame('1985-11-06', $dto->startDate);
    }
}
