<?php

declare(strict_types=1);

namespace App\DTO\Notification;

use App\Enums\NotificationType;
use App\Http\Requests\Notification\UpdateNotificationRequest;

final readonly class UpdateNotification
{
    /** @param array<string, mixed> $values */
    public function __construct(private array $values)
    {
    }

    public static function fromRequest(UpdateNotificationRequest $request): self
    {
        $validated = $request->validated();

        $values = [];

        if (\array_key_exists('lead_days', $validated)) {
            $values['lead_days'] = \intval($validated['lead_days']);
        }

        if (\array_key_exists('send_time', $validated)) {
            $values['send_time'] = $validated['send_time'];
        }

        if (\array_key_exists('target', $validated)) {
            $values['target'] = $validated['target'];
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

        if (\array_key_exists('send_time', $this->values)) {
            $values['send_time'] = $this->values['send_time'];
        }

        if (\array_key_exists('target', $this->values)) {
            $values['target'] = $this->values['target'];
        }

        if (\array_key_exists('type', $this->values)) {
            $values['type'] = $this->values['type']->value;
        }

        return $values;
    }
}
