<?php

namespace App\Shared\Rules;

use App\Shared\Support\PhoneNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NigerianPhone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! PhoneNumber::isValidNigerian($value)) {
            $fail('Enter a valid Nigerian phone number, e.g. 0803 123 4567.');
        }
    }
}
