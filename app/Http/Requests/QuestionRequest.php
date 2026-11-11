<?php

namespace App\Http\Requests;

use App\Enums\LicenseCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuestionRequest extends FormRequest
{
    public function rules(): array
    {
        $presence = $this->route('question') ? 'sometimes' : 'required';

        return [
            'ticket_number' => [$presence, 'required', 'integer', 'between:1,999'],
            'category' => [$presence, 'required', Rule::enum(LicenseCategory::class)],
            'text' => [$presence, 'required', 'string', 'max:2000'],
            'options' => [$presence, 'required', 'array', 'list', 'min:2', 'max:6'],
            'options.*' => ['required', 'string', 'max:300', 'distinct:ignore_case'],
            'correct_option' => [$presence, 'required', 'integer', 'between:0,5'],
        ];
    }
}
