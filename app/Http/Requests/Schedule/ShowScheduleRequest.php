<?php

declare(strict_types=1);

namespace App\Http\Requests\Schedule;

use Illuminate\Foundation\Http\FormRequest;

final class ShowScheduleRequest extends FormRequest
{
    public const int DEFAULT_WEEKS = 4;

    public const int MAX_WEEKS = 12;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'weeks' => ['sometimes', 'integer', 'min:1', 'max:' . self::MAX_WEEKS],
        ];
    }

    /** How many bin nights to look ahead; an empty value is the same as none. */
    public function weeks(): int
    {
        return $this->integer('weeks') ?: self::DEFAULT_WEEKS;
    }
}
