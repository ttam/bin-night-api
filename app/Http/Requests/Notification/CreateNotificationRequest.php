<?php

declare(strict_types=1);

namespace App\Http\Requests\Notification;

use Illuminate\Validation\Rule;

final class CreateNotificationRequest extends AbstractNotificationRequest
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
        $rules['name'][] = Rule::unique('notifications', 'name')->where('house_id', $this->string('house_id')->toString());

        return [
            ...$rules,

            'house_id' => ['bail', 'required', 'uuid', 'exists:houses,id'],
        ];
    }
}
