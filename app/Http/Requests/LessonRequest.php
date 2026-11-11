<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        $lesson = $this->route('lesson');

        return $lesson === null || $this->user()->can('update', $lesson);
    }

    public function rules(): array
    {
        $presence = $this->route('lesson') ? 'sometimes' : 'required';

        return [
            'type' => [$presence, 'required', Rule::in(['theory', 'driving'])],
            'study_group_id' => ['nullable', 'integer', 'exists:study_groups,id'],
            'instructor_id' => ['nullable', 'integer', 'exists:users,id'],
            'car_id' => ['nullable', 'integer', 'exists:cars,id'],
            'start_at' => [$presence, 'required', 'date'],
            'end_at' => [$presence, 'required', 'date'],
            'student_id' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }
}
