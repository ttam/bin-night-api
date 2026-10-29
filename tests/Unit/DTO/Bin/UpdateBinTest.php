<?php

declare(strict_types=1);

namespace Tests\Unit\DTO\Bin;

use App\DTO\Bin\UpdateBin;
use App\Enums\Cycle;
use App\Http\Requests\Bin\UpdateBinRequest;
use Illuminate\Contracts\Validation\Validator;
use PHPUnit\Framework\MockObject\Generator\Generator as MockGenerator;
use PHPUnit\Framework\TestCase;

final class UpdateBinTest extends TestCase
{
    public function testFromRequest(): void
    {
        $validator = new MockGenerator()->testDouble(Validator::class, false);
        $validator->method('validated')->willReturn([
            'bin_color' => '#000000',
            'cycle' => Cycle::FORTNIGHTLY->value,
            'lid_color' => '#ffffff',
            'name' => 'Example',
        ]);

        $request = new UpdateBinRequest()->setValidator($validator);

        self::assertSame([
            'bin_color' => '000000',
            'cycle' => 'fortnightly',
            'lid_color' => 'ffffff',
            'name' => 'Example',
        ], UpdateBin::fromRequest($request)->toDatabaseArray());
    }

    public function testFromRequestIgnoresANullStartDate(): void
    {
        $validator = new MockGenerator()->testDouble(Validator::class, false);
        $validator->method('validated')->willReturn(['name' => 'Example', 'start_date' => null]);

        $request = new UpdateBinRequest()->setValidator($validator);

        self::assertSame(['name' => 'Example'], UpdateBin::fromRequest($request)->toDatabaseArray());
    }

    public function testFromRequestReadsAStartDate(): void
    {
        $validator = new MockGenerator()->testDouble(Validator::class, false);
        $validator->method('validated')->willReturn(['start_date' => '1985-11-06']);

        $request = new UpdateBinRequest()->setValidator($validator);

        self::assertSame(['start_date' => '1985-11-06'], UpdateBin::fromRequest($request)->toDatabaseArray());
    }

    public function testToDatabaseArray(): void
    {
        $array = new UpdateBin([
            'bin_color' => '000000',
            'cycle' => Cycle::FORTNIGHTLY,
            'lid_color' => 'ffffff',
            'name' => 'Example',
            'start_date' => '1985-11-06',
        ])->toDatabaseArray();

        self::assertSame('1985-11-06', $array['start_date']);
        self::assertSame('000000', $array['bin_color']);
        self::assertSame('fortnightly', $array['cycle']);
        self::assertSame('ffffff', $array['lid_color']);
        self::assertSame('Example', $array['name']);
    }
}
