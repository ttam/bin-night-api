<?php

declare(strict_types=1);

namespace Tests\Unit\DTO\House;

use App\DTO\House\CreateHouse;
use App\Enums\DayOfWeek;
use App\Http\Requests\House\CreateHouseRequest;
use PHPUnit\Framework\TestCase;

final class CreateHouseTest extends TestCase
{
    public function testFromRequest(): void
    {
        $dto = CreateHouse::fromRequest(new CreateHouseRequest([
            'bin_night' => DayOfWeek::MONDAY->value,
            'name' => 'Example',
            'timezone' => 'UTC',
        ]));

        self::assertSame('monday', $dto->binNight->value);
        self::assertSame('Example', $dto->name);
        self::assertSame('UTC', $dto->timezone->getName());
    }
}
