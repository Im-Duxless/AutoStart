<?php

namespace App\Http\Requests;

use App\Enums\EnrollmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EnrollmentRequest extends FormRequest
{
    public function rules(): array
    {
        if ($this->isMethod('POST')) {
            return [
                'user_id' => ['required', 'integer', 'exists:users,id'],
                'study_group_id' => ['required', 'integer', 'exists:study_groups,id'],
            ];
        }

        return [
            'status' => ['required', Rule::in([EnrollmentStatus::Approved->value, EnrollmentStatus::Rejected->value])],
        ];
    }
}
