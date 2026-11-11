<?php

namespace App\Http\Requests;

use App\Enums\LicenseCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExamSubmitRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'category' => ['required', Rule::enum(LicenseCategory::class)],
            'ticket_number' => ['required', 'integer', 'between:1,999'],
            'version' => ['required', 'string', 'size:64'],
            'answers' => ['required', 'array', 'list', 'min:1'],
            'answers.*.question_id' => ['required', 'integer', 'min:1', 'distinct'],
            'answers.*.option' => ['required', 'integer', 'between:0,5'],
        ];
    }
}
