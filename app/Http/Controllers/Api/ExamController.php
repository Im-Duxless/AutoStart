<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExamSubmitRequest;
use App\Http\Requests\ListRequest;
use App\Http\Resources\ExamAttemptResource;
use App\Http\Resources\QuestionResource;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Services\ExamService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ExamController extends Controller
{
    public function __construct(private ExamService $exams) {}

    public function tickets(ListRequest $request): JsonResponse
    {
        $tickets = Question::query()->select('category', 'ticket_number')
            ->selectRaw('COUNT(*) AS question_count')
            ->when($request->validated('category'), fn (Builder $query, $category) => $query->where('category', $category))
            ->groupBy('category', 'ticket_number')->orderBy('category')->orderBy('ticket_number')->get();

        return response()->json(['data' => $tickets, 'pass_percent' => config('autostart.pass_percent')]);
    }

    public function ticket(ListRequest $request, int $number): JsonResponse
    {
        $questions = $this->exams->questions($number, $request->validated('category'));

        return response()->json([
            'data' => QuestionResource::collection($questions),
            'category' => $questions->first()->category->value,
            'ticket_number' => $number,
            'version' => $this->exams->version($questions),
        ]);
    }

    public function submit(ExamSubmitRequest $request): ExamAttemptResource
    {
        return new ExamAttemptResource($this->exams->submit($request->user(), $request->validated()));
    }

    public function history(ListRequest $request): AnonymousResourceCollection
    {
        $query = ExamAttempt::with('user')
            ->when($request->user()->role === UserRole::Student, fn (Builder $query) => $query->where('user_id', $request->user()->id))
            ->when($request->validated('category'), fn (Builder $query, $category) => $query->where('category', $category))
            ->when($request->validated('ticket_number'), fn (Builder $query, $number) => $query->where('ticket_number', $number))
            ->when($request->filled('passed'), fn (Builder $query) => $query->where('passed', $request->validated('passed')))
            ->when($request->filled('search'), fn (Builder $query) => $query->whereHas(
                'user', fn (Builder $user) => $user->where('name', 'like', '%'.$request->validated('search').'%'),
            ));

        return ExamAttemptResource::collection($request->paginate($query, 'created_at'));
    }

    public function show(ExamAttempt $examAttempt): ExamAttemptResource
    {
        Gate::authorize('view', $examAttempt);

        return new ExamAttemptResource($examAttempt->load('user'));
    }
}
