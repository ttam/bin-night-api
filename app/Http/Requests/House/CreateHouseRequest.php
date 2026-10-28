<?php

declare(strict_types=1);

namespace App\Http\Requests\House;

use App\Auth\AuthenticatedUser;
use App\Models\User;
use Illuminate\Validation\Rule;

final class CreateHouseRequest extends AbstractHouseRequest
{
    protected string $presence {
        get {
            return 'required';
        }
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $rules = parent::rules();

        /** @var string|null $userId */
        $userId = User::query()->where('external_id', '=', AuthenticatedUser::fromRequest($this)->id)->value('id');
        $rules['name'][] = Rule::unique('houses', 'name')->where('user_id', $userId);

        return $rules;
    }
}
