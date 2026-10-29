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
            'send_time.date_format' => 'The send time must be on a quarter hour, like 18:00:00 or 18:45:00.',
            'send_time.regex' => 'The send time must be on a quarter hour, like 18:00:00 or 18:45:00.',

            ...$this->getTargetFormat()['messages'],
        ];
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'lead_days' => [$this->presence, 'integer', 'min:0', 'max:7'],

            'name' => [$this->presence, 'string', 'max:255'],

            'send_time' => ['bail', $this->presence, 'date_format:H:i:s', 'regex:/^\d{2}:(00|15|30|45):00$/'],

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
