<?php

declare(strict_types=1);

namespace App\Http\Requests\Bin;

use App\Models\Bin;
use Illuminate\Validation\Rule;

final class UpdateBinRequest extends AbstractBinRequest
{
    protected string $presence {
        get {
            return 'sometimes';
        }
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $rules = parent::rules();

        /** @var Bin $bin */
        $bin = $this->route('bin');

        $houseId = $bin->houseId;
        $rules['name'][] = Rule::unique('bins', 'name')->where('house_id', $houseId)->ignore($bin);

        return $rules;
    }
}
