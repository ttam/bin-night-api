<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** A mobile number we can text, in E.164, for any country listed in `config/phone.php`. */
final readonly class MobileNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        /** @var list<string> $patterns */
        $patterns = \array_values(\config()->array('phone.mobile_patterns'));

        foreach ($patterns as $pattern) {
            if (\is_string($value) && \preg_match($pattern, $value) === 1) {
                return;
            }
        }

        $fail('The :attribute must be an Australian mobile, like +61400000000.');
    }
}
