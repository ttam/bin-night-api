<?php

declare(strict_types=1);

namespace App\Http\Requests\Notification;

use App\Enums\NotificationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class AbstractNotificationRequest extends FormRequest
{
    abstract protected string $presence {
        get;
    }

    public function authorize(): bool
    {
        return true;
    }

    public function messages(): array
    {
        return [
            // The schedule only looks at each half hour, so a time between them would never be sent.
            'send_time.regex' => 'The send time must be on the hour or half hour, like 18:00 or 18:30.',

            ...$this->getTargetFormat()['messages'],
        ];
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'lead_days' => [$this->presence, 'integer', 'min:0', 'max:7'],

            'name' => [$this->presence, 'string', 'max:255'],

            'send_time' => ['bail', $this->presence, 'date_format:H:i', 'regex:/^\d{2}:(00|30)$/'],

            'target' => [$this->presence, 'string', 'max:255', ...$this->getTargetFormat()['rules']],

            'type' => [$this->presence, Rule::enum(NotificationType::class)],
        ];
    }

    protected function getSubmittedType(): mixed
    {
        return $this->input('type');
    }

    private function getNotificationType(): ?NotificationType
    {
        $type = $this->getSubmittedType();

        return \is_string($type) ? NotificationType::tryFrom($type) : null;
    }

    /** @return array{messages: array<string, string>, rules: list<string>} */
    private function getTargetFormat(): array
    {
        return match ($this->getNotificationType()) {
            // Twilio needs the number in E.164 format.
            NotificationType::SMS => [
                'messages' => ['target.regex' => 'The target must be an international phone number, like +61400000000.'],
                'rules' => ['regex:/^\+[1-9]\d{6,14}$/'],
            ],

            // A missing or unknown type is reported by the type rule.
            null => ['messages' => [], 'rules' => []],
        };
    }
}
