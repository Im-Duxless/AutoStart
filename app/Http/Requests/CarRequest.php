<?php

namespace App\Http\Requests;

use App\Enums\LicenseCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CarRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('plate_number'))) {
            $this->merge(['plate_number' => mb_strtoupper(preg_replace('/\s+/u', '', $this->input('plate_number')) ?? '')]);
        }
    }

    public function rules(): array
    {
        $presence = $this->route('car') ? 'sometimes' : 'required';

        return [
            'brand' => [$presence, 'required', 'string', 'max:60'],
            'model' => [$presence, 'required', 'string', 'max:60'],
            'plate_number' => [$presence, 'required', 'string', 'max:20', 'regex:/^[\pL0-9-]+$/u', Rule::unique('cars')->ignore($this->route('car'))],
            'category' => [$presence, 'required', Rule::enum(LicenseCategory::class)],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
