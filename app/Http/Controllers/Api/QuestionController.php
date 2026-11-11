<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListRequest;
use App\Http\Requests\QuestionRequest;
use App\Http\Resources\QuestionResource;
use App\Models\Question;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuestionController extends Controller
{
    public function index(ListRequest $request): AnonymousResourceCollection
    {
        $query = Question::query()
            ->when($request->validated('category'), fn (Builder $query, $category) => $query->where('category', $category))
            ->when($request->validated('ticket_number'), fn (Builder $query, $number) => $query->where('ticket_number', $number))
            ->when($request->filled('search'), fn (Builder $query) => $query->where('text', 'like', '%'.$request->validated('search').'%'));

        return QuestionResource::collection($request->paginate($query, 'ticket_number', 'asc'));
    }

    public function show(Question $question): QuestionResource
    {
        return new QuestionResource($question);
    }

    public function store(QuestionRequest $request): QuestionResource
    {
        $question = new Question($request->validated());
        $this->validateCorrectOption($question);
        $question->save();

        return new QuestionResource($question);
    }

    public function update(QuestionRequest $request, Question $question): QuestionResource
    {
        $updated = DB::transaction(function () use ($request, $question) {
            $question = Question::query()->lockForUpdate()->findOrFail($question->id);
            $question->fill($request->validated());
            $this->validateCorrectOption($question);
            $question->save();

            return $question;
        }, 5);

        return new QuestionResource($updated);
    }

    public function destroy(Question $question): Response
    {
        $question->delete();

        return response()->noContent();
    }

    private function validateCorrectOption(Question $question): void
    {
        if (! array_key_exists((int) $question->correct_option, $question->options)) {
            throw ValidationException::withMessages(['correct_option' => 'Выберите правильный вариант из списка ответов.']);
        }
    }
}
