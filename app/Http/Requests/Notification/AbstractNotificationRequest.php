<?php

declare(strict_types=1);

namespace App\Http\Requests\Notification;

use App\Enums\NotificationType;
use App\Rules\MobileNumber;
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
        ];
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'lead_days' => [$this->presence, 'integer', 'min:0', 'max:7'],

            'name' => [$this->presence, 'string', 'max:255'],

            'send_time' => ['bail', $this->presence, 'date_format:H:i:s', 'regex:/^\d{2}:(00|15|30|45):00$/'],

            'target' => [$this->presence, 'string', 'max:255', ...$this->getTargetRules()],

            'type' => [$this->presence, Rule::enum(NotificationType::class)],
        ];
    }

    protected function getSubmittedType(): mixed
    {
        return $this->input('type');
    }

    /** @return list<MobileNumber> what a good target looks like for the notification's type */
    private function getTargetRules(): array
    {
        $type = $this->getSubmittedType();

        return match (\is_string($type) ? NotificationType::tryFrom($type) : null) {
            // Twilio needs the number in E.164 format, and we only text mobiles we have a pattern for.
            NotificationType::SMS => [new MobileNumber()],

            // A missing or unknown type is reported by the type rule.
            null => [],
        };
    }
}
