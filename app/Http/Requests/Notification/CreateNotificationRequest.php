<?php

declare(strict_types=1);

namespace App\Http\Requests\Notification;

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
        return [
            ...parent::rules(),

            'house_id' => ['bail', 'required', 'uuid', 'exists:houses,id'],
        ];
    }
}
