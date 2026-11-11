<?php

namespace App\Http\Controllers\Api;

use App\Enums\LessonStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\LessonRequest;
use App\Http\Requests\ListRequest;
use App\Http\Resources\LessonResource;
use App\Models\Lesson;
use App\Models\User;
use App\Services\LessonService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class LessonController extends Controller
{
    public function __construct(private LessonService $lessons) {}

    public function index(ListRequest $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $query = Lesson::with(Lesson::RELATIONS);

        if ($user->role === UserRole::Instructor) {
            $query->where('instructor_id', $user->id);
        }
        if ($user->role === UserRole::Student) {
            $request->validated('mode') === 'available'
                ? $this->availableTo($query, $user)
                : $this->visibleToStudent($query, $user);
        }

        foreach (['type', 'status', 'instructor_id', 'study_group_id'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->validated($field));
            }
        }
        $query->when($request->validated('upcoming'), fn (Builder $query) => $query->where('start_at', '>', now())
            ->whereIn('status', [LessonStatus::Booked->value, LessonStatus::Scheduled->value, LessonStatus::Free->value]))
            ->when($request->validated('from'), fn (Builder $query, $date) => $query->where('start_at', '>=', $date.' 00:00:00'))
            ->when($request->validated('to'), fn (Builder $query, $date) => $query->where('start_at', '<=', $date.' 23:59:59'))
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = $request->validated('search');
                $query->where(function (Builder $fields) use ($search, $request) {
                    $fields->whereHas('studyGroup', fn (Builder $group) => $group->where('name', 'like', "%$search%"))
                        ->orWhereHas('instructor', fn (Builder $user) => $user->where('name', 'like', "%$search%"))
                        ->orWhereHas('car', fn (Builder $car) => $car->where('plate_number', 'like', "%$search%"));
                    if ($request->user()->role !== UserRole::Student) {
                        $fields->orWhereHas('student', fn (Builder $user) => $user->where('name', 'like', "%$search%"));
                    }
                });
            });

        return LessonResource::collection($request->paginate($query, 'start_at', 'asc'));
    }

    public function show(Lesson $lesson): LessonResource
    {
        $lesson->load(Lesson::RELATIONS);
        Gate::authorize('view', $lesson);

        return new LessonResource($lesson);
    }

    public function store(LessonRequest $request): LessonResource
    {
        $lesson = $this->lessons->save($request->user(), $request->validated());

        return new LessonResource($lesson->load(Lesson::RELATIONS));
    }

    public function update(LessonRequest $request, Lesson $lesson): LessonResource
    {
        $updated = $this->lessons->save($request->user(), $request->validated(), $lesson);

        return new LessonResource($updated->load(Lesson::RELATIONS));
    }

    public function destroy(Request $request, Lesson $lesson): Response
    {
        $this->lessons->delete($lesson, $request->user());

        return response()->noContent();
    }

    public function book(Request $request, Lesson $lesson): LessonResource
    {
        return new LessonResource($this->lessons->book($lesson, $request->user())->load(Lesson::RELATIONS));
    }

    public function cancel(Request $request, Lesson $lesson): LessonResource
    {
        return new LessonResource($this->lessons->cancel($lesson, $request->user())->load(Lesson::RELATIONS));
    }

    public function complete(Request $request, Lesson $lesson): LessonResource
    {
        return new LessonResource($this->lessons->complete($lesson, $request->user())->load(Lesson::RELATIONS));
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = $user->role === UserRole::Student ? $user->drivingLessons() : $user->instructedLessons();
        $completed = (clone $query)->where('status', LessonStatus::Completed->value)->get(['start_at', 'end_at']);
        $minutes = $completed->sum(fn (Lesson $lesson) => $lesson->durationMinutes());

        return response()->json(['data' => [
            'completed' => $completed->count(),
            'minutes' => $minutes,
            'hours' => round($minutes / 60, 2),
            'upcoming' => $query->where('status', LessonStatus::Booked->value)->where('start_at', '>', now())->count(),
        ]]);
    }

    private function visibleToStudent(Builder $query, User $student): void
    {
        $query->where(fn (Builder $visible) => $visible->where('student_id', $student->id)
            ->orWhereIn('study_group_id', $student->approvedGroups()->select('study_groups.id')->getQuery()));
    }

    private function availableTo(Builder $query, User $student): void
    {
        $categories = $student->approvedGroups()->pluck('study_groups.category')->map(fn ($category) => $category->value);

        $query->where('type', 'driving')->where('status', LessonStatus::Free->value)
            ->whereNull('student_id')->where('start_at', '>', now())
            ->whereHas('car', fn (Builder $car) => $car->where('is_active', true)->whereIn('category', $categories))
            ->whereHas('instructor', fn (Builder $user) => $user->where('role', UserRole::Instructor->value));
    }
}
