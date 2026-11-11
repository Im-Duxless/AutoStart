<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class EnrollmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'study_group_id' => $this->study_group_id,
            'group_name' => $this->group_name,
            'category' => $this->category,
            'user_id' => $this->user_id,
            'student_name' => $this->student_name,
            'status' => $this->status,
            'created_at' => Carbon::parse($this->created_at)->toIso8601String(),
            'updated_at' => Carbon::parse($this->updated_at)->toIso8601String(),
        ];
    }
}
