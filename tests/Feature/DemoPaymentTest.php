<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\StudyGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DemoPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_card_is_idempotent_and_only_charges_the_signed_in_student(): void
    {
        $student = $this->enrolledStudent();
        $other = $this->enrolledStudent();
        Sanctum::actingAs($student);
        $payload = $this->payment('card', '230.45');
        $id = $this->postJson('/api/payments/demo', $payload + ['user_id' => $other->id, 'status' => 'void'])
            ->assertCreated()->assertJsonPath('data.user_id', $student->id)
            ->assertJsonPath('data.status', 'received')->assertJsonPath('data.is_demo', true)->json('data.id');

        $this->postJson('/api/payments/demo', $payload)->assertOk()->assertJsonPath('data.id', $id);
        $this->postJson('/api/payments/demo', array_replace($payload, ['amount' => '0.01']))->assertConflict();
        $this->postJson('/api/payments/demo', $this->payment('card', '769.56'))->assertConflict();
        $this->postJson('/api/payments/demo', $this->payment('card', '1.001'))->assertUnprocessable();
        $this->getJson('/api/payments/balance')->assertOk()->assertJsonPath('data.paid', '230.45')
            ->assertJsonPath('data.due', '769.55')->assertJsonPath('data.available', '769.55');
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_pending_cash_reserves_the_balance_and_only_the_admin_can_confirm_it(): void
    {
        $student = $this->enrolledStudent();
        Sanctum::actingAs($student);
        $id = $this->postJson('/api/payments/demo', $this->payment('cash', '650.00'))
            ->assertCreated()->assertJsonPath('data.status', 'pending')->json('data.id');
        $this->getJson('/api/payments/balance')->assertOk()->assertJsonPath('data.paid', '0.00')
            ->assertJsonPath('data.due', '1000.00')->assertJsonPath('data.pending', '650.00')
            ->assertJsonPath('data.available', '350.00');
        $this->postJson('/api/payments/demo', $this->payment('card', '350.01'))->assertConflict();
        $this->postJson("/api/payments/$id/confirm")->assertForbidden();

        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/payments/$id/cancel")->assertForbidden();
        $this->getJson('/api/payments?status=pending')->assertJsonCount(0, 'data');

        Sanctum::actingAs(User::factory()->admin()->create());
        $this->getJson('/api/payments?status=pending')->assertOk()->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('received_total', '0.00');
        $this->postJson('/api/payments', [
            'user_id' => $student->id, 'amount' => '350.01', 'payment_date' => today()->toDateString(), 'payment_method' => 'cash',
        ])->assertConflict();
        $this->patchJson("/api/payments/$id", ['status' => 'received'])->assertConflict();
        $this->deleteJson("/api/payments/$id")->assertConflict();
        $this->postJson("/api/payments/$id/confirm")->assertOk()->assertJsonPath('data.status', 'received');
        $this->postJson("/api/payments/$id/confirm")->assertOk()->assertJsonPath('data.id', $id);
        $this->postJson("/api/payments/$id/cancel")->assertConflict();
        $this->getJson('/api/reports/summary')->assertOk()->assertJsonPath('data.received_payments', '650.00');

        Sanctum::actingAs($student);
        $this->getJson('/api/payments/balance')->assertOk()->assertJsonPath('data.paid', '650.00')
            ->assertJsonPath('data.due', '350.00')->assertJsonPath('data.pending', '0.00');
    }

    public function test_cancelling_a_cash_request_restores_available_amount_without_creating_a_payment(): void
    {
        $student = $this->enrolledStudent();
        Sanctum::actingAs($student);
        $payload = $this->payment('cash', '1000.00');
        $id = $this->postJson('/api/payments/demo', $payload)->assertCreated()->json('data.id');
        $this->getJson('/api/payments/balance')->assertOk()->assertJsonPath('data.available', '0.00');
        $this->postJson("/api/payments/$id/cancel")->assertOk()->assertJsonPath('data.status', 'void');
        $this->postJson('/api/payments/demo', $payload)->assertOk()
            ->assertJsonPath('data.id', $id)->assertJsonPath('data.status', 'void');
        $this->getJson('/api/payments/balance')->assertOk()->assertJsonPath('data.available', '1000.00')
            ->assertJsonPath('data.paid', '0.00')->assertJsonPath('data.pending', '0.00');
        $this->assertSame(1, Payment::query()->count());

        Sanctum::actingAs(User::factory()->admin()->create());
        $this->postJson("/api/payments/$id/confirm")->assertConflict();
        $this->postJson('/api/payments/demo', $this->payment('card', '10'))->assertForbidden();

        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/payments/demo', $this->payment('card', '10'))->assertConflict();
    }

    private function enrolledStudent(): User
    {
        $student = User::factory()->create();
        $group = StudyGroup::factory()->create(['tuition' => '1000.00']);
        $group->students()->attach($student, ['status' => 'approved']);

        return $student;
    }

    private function payment(string $method, string $amount): array
    {
        return ['payment_method' => $method, 'amount' => $amount, 'reference' => (string) Str::uuid()];
    }
}
