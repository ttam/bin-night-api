<?php

declare(strict_types=1);

namespace App\Http\Requests\Notification;

use App\Models\Notification;

final class UpdateNotificationRequest extends AbstractNotificationRequest
{
    protected string $presence {
        get {
            return 'sometimes';
        }
    }

    /** A patch may not send a "type", in which case we use the type from the notification we're editing. */
    protected function getSubmittedType(): mixed
    {
        $notification = $this->route('notification');

        return $this->input('type')
            ?? ($notification instanceof Notification ? $notification->getAttribute('type') : null);
    }
}
