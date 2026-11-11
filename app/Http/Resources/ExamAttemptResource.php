<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExamAttemptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'student' => $this->whenLoaded('user', fn () => $this->user->only('id', 'name')),
            'category' => $this->category->value,
            'ticket_number' => $this->ticket_number,
            'total_questions' => $this->total_questions,
            'correct_answers' => $this->correct_answers,
            'percent' => intdiv(100 * $this->correct_answers, max(1, $this->total_questions)),
            'passed' => $this->passed,
            'answers' => $this->when($request->routeIs('exams.submit', 'exams.show'), $this->answers),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
