<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Question;
use App\Models\StudyGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExamAndPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_exam_is_scored_by_server_and_history_keeps_the_original_questions(): void
    {
        $student = User::factory()->create();
        $question = Question::factory()->create();
        $second = Question::factory()->create(['text' => 'Как безопасно начать движение?', 'correct_option' => 1]);
        Sanctum::actingAs($student);
        $ticket = $this->getJson('/api/tickets/1?category=B')->assertOk()
            ->assertJsonMissingPath('data.0.correct_option')->assertJsonCount(2, 'data');
        $body = ['ticket_number' => 1, 'category' => 'B', 'version' => $ticket->json('version')];

        $this->postJson('/api/exams/submit', $body + ['answers' => [['question_id' => $question->id, 'option' => 0]]])
            ->assertUnprocessable()->assertJsonValidationErrors('answers');
        $this->postJson('/api/exams/submit', $body + ['answers' => [
            ['question_id' => $question->id, 'option' => 0], ['question_id' => $second->id, 'option' => 5],
        ]])->assertUnprocessable();

        $payload = $body + ['answers' => [
            ['question_id' => $question->id, 'option' => 0], ['question_id' => $second->id, 'option' => 0],
        ]];
        $id = $this->postJson('/api/exams/submit', $payload)->assertCreated()->assertJsonPath('data.correct_answers', 1)
            ->assertJsonPath('data.percent', 50)->assertJsonPath('data.passed', false)->json('data.id');
        $this->assertDatabaseHas('exam_attempts', ['id' => $id, 'user_id' => $student->id]);
        $question->update(['text' => 'Другой вопрос']);
        $this->postJson('/api/exams/submit', $payload)->assertConflict();
        $question->delete();
        $this->getJson("/api/exams/history/$id")->assertOk()->assertJsonPath('data.answers.0.correct_option', 0)
            ->assertJsonPath('data.answers.0.text', 'Что помогает сохранить запас времени для остановки?');
        $this->getJson('/api/exams/history')->assertJsonMissingPath('data.0.answers');

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/exams/history/$id")->assertForbidden();
        $this->getJson('/api/exams/history')->assertJsonCount(0, 'data');
    }

    public function test_payments_are_scoped_and_totals_exclude_void_entries(): void
    {
        $student = User::factory()->create();
        $other = User::factory()->create();
        $group = StudyGroup::factory()->create(['tuition' => '1000.00']);
        $group->students()->attach($student, ['status' => 'approved']);
        $otherPayment = Payment::factory()->create(['user_id' => $other->id, 'amount' => '200.00']);
        $day = today()->toDateString();

        Sanctum::actingAs(User::factory()->admin()->create());
        $payload = ['user_id' => $student->id, 'amount' => '120.55', 'payment_date' => $day, 'payment_method' => 'transfer'];
        $payment = $this->postJson('/api/payments', $payload)->assertCreated()->json('data.id');
        $void = $this->postJson('/api/payments', array_replace($payload, ['status' => 'void']))->assertCreated()->json('data.id');
        $this->patchJson("/api/payments/$payment", ['amount' => '140.11'])->assertOk()->assertJsonPath('data.amount', '140.11');
        $this->postJson('/api/payments', array_replace($payload, ['amount' => '1.001']))->assertUnprocessable();
        $this->getJson("/api/payments?user_id={$student->id}&payment_method=transfer&from=$day&to=$day")
            ->assertOk()->assertJsonPath('received_total', '140.11')->assertJsonPath('meta.total', 2);
        $this->getJson('/api/reports/summary')->assertOk()->assertJsonPath('data.received_payments', '340.11');

        Sanctum::actingAs($student);
        $this->getJson('/api/payments/balance')->assertOk()->assertJsonPath('data.paid', '140.11')->assertJsonPath('data.due', '859.89');
        $this->getJson("/api/payments/{$otherPayment->id}")->assertForbidden();
        $this->getJson('/api/payments')->assertJsonCount(2, 'data');
        $this->postJson('/api/payments', $payload)->assertForbidden();

        Sanctum::actingAs(User::factory()->admin()->create());
        $this->deleteJson("/api/payments/$void")->assertNoContent();
        $this->assertDatabaseMissing('payments', ['id' => $void]);
    }
}
