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

    public function rules(): array
    {
        $rules = parent::rules();

        $bin = $this->route('bin');

        if ($bin instanceof Bin) {
            $rules['name'][] = Rule::unique('bins', 'name')
                ->where('house_id', $bin->getAttribute('house_id'))
                ->ignore($bin);
        }

        return $rules;
    }
}
