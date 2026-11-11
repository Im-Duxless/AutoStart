<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudyGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category->value,
            'capacity' => $this->capacity,
            'approved_count' => $this->whenCounted('approvedStudents'),
            'status' => $this->status,
            'start_date' => $this->start_date->toDateString(),
            'tuition' => $this->tuition,
        ];
    }
}
