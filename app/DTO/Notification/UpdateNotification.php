<?php

declare(strict_types=1);

namespace App\DTO\Notification;

use App\Enums\NotificationType;
use App\Http\Requests\Notification\UpdateNotificationRequest;

final readonly class UpdateNotification
{
    /** @param array{lead_days?: int, name?: string, phone_number_id?: string, send_time?: string, type?: NotificationType} $values */
    public function __construct(private array $values)
    {
    }

    /** @param string|null $phoneNumberId the destination for a new `target`, which the controller has already found */
    public static function fromRequest(UpdateNotificationRequest $request, ?string $phoneNumberId = null): self
    {
        /** @var array{lead_days?: int|string, name?: string, send_time?: string, type?: string} $validated */
        $validated = $request->validated();

        $values = [];

        if (\array_key_exists('lead_days', $validated)) {
            $values['lead_days'] = \intval($validated['lead_days']);
        }

        if (\array_key_exists('name', $validated)) {
            $values['name'] = $validated['name'];
        }

        if ($phoneNumberId !== null) {
            $values['phone_number_id'] = $phoneNumberId;
        }

        if (\array_key_exists('send_time', $validated)) {
            $values['send_time'] = $validated['send_time'];
        }

        if (\array_key_exists('type', $validated)) {
            $values['type'] = NotificationType::from($validated['type']);
        }

        return new self($values);
    }

    /** @return array<string, mixed> */
    public function toDatabaseArray(): array
    {
        $values = [];

        if (\array_key_exists('lead_days', $this->values)) {
            $values['lead_days'] = $this->values['lead_days'];
        }

        if (\array_key_exists('name', $this->values)) {
            $values['name'] = $this->values['name'];
        }

        if (\array_key_exists('phone_number_id', $this->values)) {
            $values['phone_number_id'] = $this->values['phone_number_id'];
        }

        if (\array_key_exists('send_time', $this->values)) {
            $values['send_time'] = $this->values['send_time'];
        }

        if (\array_key_exists('type', $this->values)) {
            $values['type'] = $this->values['type']->value;
        }

        return $values;
    }
}
