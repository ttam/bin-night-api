<?php

declare(strict_types=1);

namespace App\Http\Requests\PhoneNumber;

use Illuminate\Foundation\Http\FormRequest;

final class CreatePhoneNumberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function messages(): array
    {
        return ['number.regex' => 'The number must be an Australian mobile, like +61400000000.'];
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        /** @var list<string> $patterns */
        $patterns = \array_values(\config()->array('phone.mobile_patterns'));

        return [
            'number' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail) use ($patterns): void {
                foreach ($patterns as $pattern) {
                    if (\is_string($value) && \preg_match($pattern, $value) === 1) {
                        return;
                    }
                }

                $fail($this->messages()['number.regex']);
            }],
        ];
    }
}
