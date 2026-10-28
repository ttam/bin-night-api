<?php

declare(strict_types=1);

namespace App\DTO\House;

use App\Enums\DayOfWeek;
use App\Http\Requests\House\UpdateHouseRequest;
use DateTimeZone;

final readonly class UpdateHouse
{
    /** @param array{bin_night?: DayOfWeek, name?: string, timezone?: DateTimeZone} $values */
    public function __construct(private array $values)
    {
    }

    public static function fromRequest(UpdateHouseRequest $request): self
    {
        /** @var array{bin_night?: string, name?: string, timezone?: string} $validated */
        $validated = $request->validated();

        $values = [];

        if (\array_key_exists('bin_night', $validated)) {
            $values['bin_night'] = DayOfWeek::from($validated['bin_night']);
        }

        if (\array_key_exists('name', $validated)) {
            $values['name'] = $validated['name'];
        }

        if (\array_key_exists('timezone', $validated)) {
            $values['timezone'] = new DateTimeZone($validated['timezone']);
        }

        return new self($values);
    }

    /** @return array<string, mixed> */
    public function toDatabaseArray(): array
    {
        $values = [];

        if (\array_key_exists('bin_night', $this->values)) {
            $values['bin_night'] = $this->values['bin_night']->value;
        }

        if (\array_key_exists('name', $this->values)) {
            $values['name'] = $this->values['name'];
        }

        if (\array_key_exists('timezone', $this->values)) {
            $values['timezone'] = $this->values['timezone']->getName();
        }

        return $values;
    }
}
