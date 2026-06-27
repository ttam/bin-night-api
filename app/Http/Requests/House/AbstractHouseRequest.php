<?php

declare(strict_types=1);

namespace App\Http\Requests\House;

use App\Enums\DayOfWeek;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class AbstractHouseRequest extends FormRequest
{
    abstract protected string $presence {
        get;
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bin_night' => [$this->presence, Rule::enum(DayOfWeek::class)],

            'name' => [$this->presence, 'string', 'max:255'],

            'timezone' => [$this->presence, 'string', 'timezone'],
        ];
    }
}
