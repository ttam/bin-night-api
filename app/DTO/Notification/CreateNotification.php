<?php

declare(strict_types=1);

namespace App\DTO\Notification;

use App\Enums\NotificationType;
use App\Http\Requests\Notification\CreateNotificationRequest;

final readonly class CreateNotification
{
    public function __construct(
        public string $houseId,
        public int $leadDays,
        public string $name,
        public string $phoneNumberId,
        public string $sendTime,
        public NotificationType $type,
    ) {
    }

    public static function fromRequest(CreateNotificationRequest $request, string $phoneNumberId): self
    {
        return new self(
            $request->string('house_id')->toString(),
            $request->integer('lead_days'),
            $request->string('name')->toString(),
            $phoneNumberId,
            $request->string('send_time')->toString(),
            NotificationType::from($request->string('type')->toString()),
        );
    }
}
