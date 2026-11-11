<?php

namespace App\Http\Resources;

use App\Enums\LessonStatus;
use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $actor = $request->user();

        return [
            'id' => $this->id,
            'type' => $this->type,
            'status' => $this->status->value,
            'study_group_id' => $this->study_group_id,
            'instructor_id' => $this->instructor_id,
            'student_id' => $this->student_id,
            'car_id' => $this->car_id,
            'start_at' => $this->start_at->toIso8601String(),
            'end_at' => $this->end_at->toIso8601String(),
            'minutes' => $this->durationMinutes(),
            'study_group' => $this->whenLoaded('studyGroup', fn () => new StudyGroupResource($this->studyGroup)),
            'car' => $this->whenLoaded('car', fn () => new CarResource($this->car)),
            'instructor' => $this->whenLoaded('instructor', fn () => $this->instructor->only('id', 'name')),
            'student' => $this->whenLoaded('student', fn () => $this->student->only('id', 'name')),
            'can_book' => $actor->role === UserRole::Student && $this->status === LessonStatus::Free && $this->start_at->isFuture(),
            'can_cancel' => $actor->can('cancel', $this->resource) && $this->start_at->isFuture()
                && in_array($this->status, [LessonStatus::Scheduled, LessonStatus::Free, LessonStatus::Booked], true),
            'can_complete' => $actor->can('complete', $this->resource) && $this->status === LessonStatus::Booked && ! $this->end_at->isFuture(),
        ];
    }
}
