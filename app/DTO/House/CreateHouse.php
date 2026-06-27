<?php

declare(strict_types=1);

namespace App\DTO\House;

use App\Enums\DayOfWeek;
use App\Http\Requests\House\CreateHouseRequest;
use DateTimeZone;

final readonly class CreateHouse
{
    public function __construct(
        public DayOfWeek $binNight,
        public string $name,
        public DateTimeZone $timezone,
    ) {
    }

    public static function fromRequest(CreateHouseRequest $request): self
    {
        return new self(
            DayOfWeek::from($request->input('bin_night')),
            $request->string('name')->toString(),
            new DateTimeZone($request->input('timezone')),
        );
    }
}
