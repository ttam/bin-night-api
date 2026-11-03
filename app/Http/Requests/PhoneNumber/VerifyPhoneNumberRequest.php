<?php

declare(strict_types=1);

namespace App\Http\Requests\PhoneNumber;

use Illuminate\Foundation\Http\FormRequest;

final class VerifyPhoneNumberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return ['code' => ['required', 'string', 'regex:/^\d{4,10}$/']];
    }
}
