<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower($this->input('email'))]);
        }
    }

    public function rules(): array
    {
        $presence = $this->route('user') ? 'sometimes' : 'required';

        return [
            'name' => [$presence, 'required', 'string', 'max:100'],
            'email' => [$presence, 'required', 'email', 'max:255', Rule::unique('users')->ignore($this->route('user'))],
            'role' => [$presence, 'required', Rule::enum(UserRole::class)],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9 ()-]{7,32}$/'],
            'password' => [$presence, 'required', 'string', 'min:8', 'max:72', 'regex:/\A[^\x00-\x1F\x7F]{1,72}\z/', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return ['password.regex' => 'Используйте пароль до 72 байт без управляющих символов.'];
    }
}
