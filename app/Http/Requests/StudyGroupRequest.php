<?php

namespace App\Http\Requests;

use App\Enums\LicenseCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudyGroupRequest extends FormRequest
{
    public function rules(): array
    {
        $presence = $this->route('study_group') ? 'sometimes' : 'required';

        return [
            'name' => [$presence, 'required', 'string', 'max:100', Rule::unique('study_groups')->ignore($this->route('study_group'))],
            'category' => [$presence, 'required', Rule::enum(LicenseCategory::class)],
            'capacity' => [$presence, 'required', 'integer', 'between:1,500'],
            'status' => [$presence, 'required', Rule::in(['open', 'in_progress', 'closed'])],
            'start_date' => [$presence, 'required', 'date_format:Y-m-d'],
            'tuition' => ['nullable', 'numeric', 'decimal:0,2', 'between:0,99999999.99'],
        ];
    }
}
