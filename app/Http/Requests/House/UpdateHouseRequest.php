<?php

declare(strict_types=1);

namespace App\Http\Requests\House;

use App\Models\House;
use Illuminate\Validation\Rule;

final class UpdateHouseRequest extends AbstractHouseRequest
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

        /** @var House $house */
        $house = $this->route('house');

        $userId = $house->userId;
        $rules['name'][] = Rule::unique('houses', 'name')->where('user_id', $userId)->ignore($house);

        return $rules;
    }
}
