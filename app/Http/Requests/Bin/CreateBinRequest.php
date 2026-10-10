<?php

declare(strict_types=1);

namespace App\Http\Requests\Bin;

use Illuminate\Validation\Rule;

final class CreateBinRequest extends AbstractBinRequest
{
    protected string $presence {
        get {
            return 'required';
        }
    }

    public function rules(): array
    {
        $rules = parent::rules();
        $rules['name'][] = Rule::unique('bins', 'name')->where('house_id', $this->input('house_id'));

        return [
            ...$rules,

            'house_id' => ['bail', 'required', 'uuid', 'exists:houses,id'],
        ];
    }
}
