<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Exceptions\BusinessException;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExamService
{
    public function questions(int $ticket, ?string $category): Collection
    {
        $questions = Question::query()->where('ticket_number', $ticket)
            ->when($category, fn ($query) => $query->where('category', $category))
            ->orderBy('id')->get();
        abort_if($questions->isEmpty(), 404);
        if ($questions->pluck('category.value')->unique()->count() > 1) {
            throw ValidationException::withMessages(['category' => 'Укажите категорию билета.']);
        }

        return $questions;
    }

    public function version(Collection $questions): string
    {
        $content = $questions->map(fn (Question $question) => [
            $question->id, $question->category->value, $question->ticket_number,
            $question->text, $question->options, $question->correct_option,
        ])->all();

        return hash_hmac('sha256', json_encode($content, JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    public function submit(User $actor, array $data): ExamAttempt
    {
        $questions = $this->questions($data['ticket_number'], $data['category']);
        if (! hash_equals($this->version($questions), $data['version'])) {
            throw new BusinessException('Билет изменился. Откройте его заново и ответьте на обновлённые вопросы.');
        }

        $submitted = collect($data['answers'])->keyBy('question_id');
        if ($submitted->count() !== $questions->count()) {
            throw ValidationException::withMessages(['answers' => 'Ответьте на каждый вопрос билета.']);
        }

        $answers = [];
        $correct = 0;
        foreach ($questions as $question) {
            $answer = $submitted->get($question->id);
            if ($answer === null || ! array_key_exists((int) $answer['option'], $question->options)) {
                throw ValidationException::withMessages(['answers' => 'Один из ответов не относится к этому билету.']);
            }
            $selected = (int) $answer['option'];
            $correct += (int) ($selected === (int) $question->correct_option);
            // Сохраняем вопрос и ответы, чтобы разбор оставался доступен после правок билета.
            $answers[] = [
                'question_id' => $question->id,
                'text' => $question->text,
                'options' => $question->options,
                'selected_option' => $selected,
                'correct_option' => (int) $question->correct_option,
            ];
        }

        return DB::transaction(function () use ($actor, $data, $answers, $correct) {
            $student = User::query()->lockForUpdate()->findOrFail($actor->id);
            abort_unless($student->role === UserRole::Student, 403);

            return $student->examAttempts()->create([
                'category' => $data['category'],
                'ticket_number' => $data['ticket_number'],
                'answers' => $answers,
                'total_questions' => count($answers),
                'correct_answers' => $correct,
                'passed' => $correct * 100 >= config('autostart.pass_percent') * count($answers),
            ]);
        }, 5);
    }
}
