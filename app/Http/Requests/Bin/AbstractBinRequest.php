<?php

declare(strict_types=1);

namespace App\Http\Requests\Bin;

use App\Enums\Cycle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class AbstractBinRequest extends FormRequest
{
    abstract protected string $presence {
        get;
    }

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'bin_color' => [$this->presence, 'hex_color'],

            'cycle' => [$this->presence, Rule::in(\array_map(static fn (Cycle $cycle): string => $cycle->value, Cycle::cases()))],

            'lid_color' => [$this->presence, 'hex_color'],

            'name' => [$this->presence, 'string', 'max:255'],

            'start_date' => [$this->presence, 'date_format:Y-m-d'],
        ];
    }
}
