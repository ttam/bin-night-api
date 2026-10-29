<?php

declare(strict_types=1);

namespace App\DTO\Bin;

use App\Enums\Cycle;
use App\Http\Requests\Bin\UpdateBinRequest;

final readonly class UpdateBin
{
    /** @param array{bin_color?: string, cycle?: Cycle, lid_color?: string, name?: string, start_date?: string} $values */
    public function __construct(private array $values)
    {
    }

    public static function fromRequest(UpdateBinRequest $request): self
    {
        /** @var array{bin_color?: string, cycle?: string, lid_color?: string, name?: string, start_date?: string|null} $validated */
        $validated = $request->validated();

        $values = [];

        if (\array_key_exists('bin_color', $validated)) {
            $values['bin_color'] = \ltrim($validated['bin_color'], '#');
        }

        if (\array_key_exists('cycle', $validated)) {
            $values['cycle'] = Cycle::from($validated['cycle']);
        }

        if (\array_key_exists('lid_color', $validated)) {
            $values['lid_color'] = \ltrim($validated['lid_color'], '#');
        }

        if (\array_key_exists('name', $validated)) {
            $values['name'] = $validated['name'];
        }

        // isset rather than array_key_exists because we don't want the value to be null
        if (isset($validated['start_date'])) {
            $values['start_date'] = $validated['start_date'];
        }

        return new self($values);
    }

    /** @return array<string, mixed> */
    public function toDatabaseArray(): array
    {
        $values = [];

        if (\array_key_exists('bin_color', $this->values)) {
            $values['bin_color'] = $this->values['bin_color'];
        }

        if (\array_key_exists('cycle', $this->values)) {
            $values['cycle'] = $this->values['cycle']->value;
        }

        if (\array_key_exists('lid_color', $this->values)) {
            $values['lid_color'] = $this->values['lid_color'];
        }

        if (\array_key_exists('name', $this->values)) {
            $values['name'] = $this->values['name'];
        }

        if (\array_key_exists('start_date', $this->values)) {
            $values['start_date'] = $this->values['start_date'];
        }

        return $values;
    }
}
