<?php

namespace App\Shared\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafeSlug implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^[a-z0-9](?:[a-z0-9-]{1,28}[a-z0-9])$/', $value)) {
            $fail('Use 3–30 letters, numbers or hyphens, starting and ending with a letter or number.');

            return;
        }

        if (in_array($value, config('mypage.reserved_slugs'), true)) {
            $fail('That name is reserved.');
        }
    }
}
