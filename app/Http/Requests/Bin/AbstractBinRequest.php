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

    public function messages(): array
    {
        return [
            'bin_color.regex' => 'The bin colour must be a colour like #0061a4.',
            'lid_color.regex' => 'The lid colour must be a colour like #0061a4.',
        ];
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            // Exactly #rrggbb: the columns hold six characters, and the shorter and longer forms hex_color allows do not fit. The D
            // matters: without it `$` also matches before a final newline, and "#aabbcc\n" would get through.
            'bin_color' => [$this->presence, 'regex:/^#[0-9a-fA-F]{6}$/D'],

            'cycle' => [$this->presence, Rule::in(\array_map(static fn (Cycle $cycle): string => $cycle->value, Cycle::cases()))],

            'lid_color' => [$this->presence, 'regex:/^#[0-9a-fA-F]{6}$/D'],

            'name' => [$this->presence, 'string', 'max:255'],

            'start_date' => [$this->presence, 'date_format:Y-m-d'],
        ];
    }
}
