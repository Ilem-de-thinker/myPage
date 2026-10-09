<?php

namespace App\Domains\Auth\Http\Requests;

use App\Shared\Rules\NigerianPhone;
use App\Shared\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('phone')) {
            $this->merge(['phone' => PhoneNumber::normalize($this->input('phone'))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:sellers,email'],
            'phone' => ['required', new NigerianPhone, 'unique:sellers,phone'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }
}
