<?php

declare(strict_types=1);

namespace App\Http\Requests\Bin;

final class CreateBinRequest extends AbstractBinRequest
{
    protected string $presence {
        get {
            return 'required';
        }
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),

            'house_id' => ['bail', 'required', 'uuid', 'exists:houses,id'],
        ];
    }
}
