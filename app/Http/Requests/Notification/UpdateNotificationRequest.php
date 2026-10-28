<?php

declare(strict_types=1);

namespace App\Http\Requests\Notification;

use App\Models\Notification;
use Illuminate\Validation\Rule;

final class UpdateNotificationRequest extends AbstractNotificationRequest
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

        /** @var Notification $notification */
        $notification = $this->route('notification');

        $houseId = $notification->houseId;
        $rules['name'][] = Rule::unique('notifications', 'name')->where('house_id', $houseId)->ignore($notification);

        return $rules;
    }

    /** A patch may not send a "type", in which case we use the type from the notification we're editing. */
    protected function getSubmittedType(): mixed
    {
        /** @var Notification $notification */
        $notification = $this->route('notification');

        return $this->input('type') ?? $notification->getAttribute('type');
    }
}
