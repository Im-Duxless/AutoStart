<?php

namespace App\Http\Controllers\Api;

use App\Enums\EnrollmentStatus;
use App\Enums\LessonStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListRequest;
use App\Models\Lesson;
use App\Models\Payment;
use App\Models\StudyGroup;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function summary(): JsonResponse
    {
        return response()->json(['data' => [
            'students' => User::where('role', UserRole::Student->value)->count(),
            'instructors' => User::where('role', UserRole::Instructor->value)->count(),
            'groups' => StudyGroup::count(),
            'planned_lessons' => Lesson::whereIn('status', [
                LessonStatus::Scheduled->value, LessonStatus::Free->value, LessonStatus::Booked->value,
            ])->where('start_at', '>', now())->count(),
            'received_payments' => (string) BigDecimal::of((string) Payment::where('status', 'received')->sum('amount'))->toScale(2, RoundingMode::HalfUp),
            'pending_enrollments' => DB::table('study_group_user')->where('status', EnrollmentStatus::Pending->value)->count(),
        ]]);
    }

    public function instructors(ListRequest $request): JsonResponse
    {
        $query = Lesson::where('status', LessonStatus::Completed->value)->where('type', 'driving')
            ->when($request->validated('from'), fn ($query, $date) => $query->where('end_at', '>=', $date.' 00:00:00'))
            ->when($request->validated('to'), fn ($query, $date) => $query->where('end_at', '<=', $date.' 23:59:59'));
        $byInstructor = $query->get(['instructor_id', 'start_at', 'end_at'])->groupBy('instructor_id');

        $rows = User::where('role', UserRole::Instructor->value)->orderBy('name')->get(['id', 'name'])
            ->map(function (User $instructor) use ($byInstructor) {
                $lessons = $byInstructor->get($instructor->id, collect());
                $minutes = $lessons->sum(fn (Lesson $lesson) => $lesson->durationMinutes());

                return [
                    'id' => $instructor->id,
                    'name' => $instructor->name,
                    'completed' => $lessons->count(),
                    'minutes' => $minutes,
                    'hours' => round($minutes / 60, 2),
                ];
            });

        return response()->json(['data' => $rows]);
    }
}
