<?php

declare(strict_types=1);

namespace App\Http\Requests\PhoneNumber;

use App\Rules\MobileNumber;
use Illuminate\Foundation\Http\FormRequest;

final class CreatePhoneNumberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return ['number' => ['required', 'string', new MobileNumber()]];
    }
}
