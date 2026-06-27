<?php

declare(strict_types=1);

namespace Tests\Unit\DTO\House;

use App\DTO\House\UpdateHouse;
use App\Enums\DayOfWeek;
use App\Http\Requests\House\UpdateHouseRequest;
use DateTimeZone;
use Illuminate\Contracts\Validation\Validator;
use PHPUnit\Framework\MockObject\Generator\Generator as MockGenerator;
use PHPUnit\Framework\TestCase;

final class UpdateHouseTest extends TestCase
{
    public function testFromRequest(): void
    {
        $validator = new MockGenerator()->testDouble(Validator::class, false);
        $validator->method('validated')->willReturn([
            'bin_night' => DayOfWeek::MONDAY->value,
            'name' => 'Example',
            'timezone' => 'UTC',
        ]);

        $request = new UpdateHouseRequest()->setValidator($validator);

        $array = UpdateHouse::fromRequest($request)->toDatabaseArray();

        self::assertSame('monday', $array['bin_night']);
        self::assertSame('Example', $array['name']);
        self::assertSame('UTC', $array['timezone']);
    }

    public function testToDatabaseArray(): void
    {
        $array = new UpdateHouse([
            'bin_night' => DayOfWeek::MONDAY,
            'name' => 'Example',
            'timezone' => new DateTimeZone('UTC'),
        ])->toDatabaseArray();

        self::assertSame('monday', $array['bin_night']);
        self::assertSame('Example', $array['name']);
        self::assertSame('UTC', $array['timezone']);
    }
}
