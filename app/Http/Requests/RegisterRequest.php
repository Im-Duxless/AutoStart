<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower($this->input('email'))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9 ()-]{7,32}$/'],
            'password' => ['required', 'string', 'min:8', 'max:72', 'regex:/\A[^\x00-\x1F\x7F]{1,72}\z/', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return ['password.regex' => 'Используйте пароль до 72 байт без управляющих символов.'];
    }
}
