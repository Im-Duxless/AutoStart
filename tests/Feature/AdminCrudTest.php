<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\StudyGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_manages_users_groups_and_cars(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $user = $this->postJson('/api/users', [
            'name' => 'Иван Инструкторов', 'email' => 'ivan@example.test', 'role' => 'instructor',
            'password' => 'StrongPass123', 'password_confirmation' => 'StrongPass123',
        ])->assertCreated()->assertJsonPath('data.role', 'instructor')->json('data.id');
        $this->patchJson("/api/users/$user", ['role' => 'student'])->assertOk()->assertJsonPath('data.role', 'student');
        $this->getJson("/api/users/$user")->assertOk()->assertJsonMissingPath('data.password');
        $this->deleteJson("/api/users/$user")->assertNoContent();

        $group = $this->postJson('/api/study-groups', [
            'name' => 'Утренняя B', 'category' => 'B', 'capacity' => 5, 'status' => 'open',
            'start_date' => today()->addDays(5)->toDateString(), 'tuition' => '125000.00',
        ])->assertCreated()->assertJsonPath('data.approved_count', 0)->json('data.id');
        $this->patchJson("/api/study-groups/$group", ['capacity' => 7])->assertOk()->assertJsonPath('data.capacity', 7);
        $this->getJson("/api/study-groups/$group")->assertOk();
        $this->deleteJson("/api/study-groups/$group")->assertNoContent();
        $this->getJson("/api/study-groups/$group")->assertNotFound();

        $payload = ['brand' => 'Kia', 'model' => 'Rio', 'plate_number' => 'aa 123', 'category' => 'B'];
        $car = $this->postJson('/api/cars', $payload)->assertCreated()->assertJsonPath('data.plate_number', 'AA123')->json('data.id');
        $this->postJson('/api/cars', $payload)->assertUnprocessable()->assertJsonValidationErrors('plate_number');
        $this->patchJson("/api/cars/$car", ['model' => 'Ceed', 'is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);
        $this->getJson("/api/cars/$car")->assertOk()->assertJsonPath('data.model', 'Ceed');
        $this->deleteJson("/api/cars/$car")->assertNoContent();
    }

    public function test_query_filters_search_sort_and_paginate(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        Car::factory()->create(['brand' => 'Kia', 'plate_number' => 'BA123', 'category' => 'B']);
        Car::factory()->create(['brand' => 'Kia', 'plate_number' => 'BA456', 'category' => 'B']);
        Car::factory()->create(['brand' => 'Kia', 'plate_number' => 'BA789', 'category' => 'B', 'is_active' => false]);
        Car::factory()->create(['brand' => 'Kia', 'plate_number' => 'CA123', 'category' => 'C']);

        $this->getJson('/api/cars?search=Kia&category=B&is_active=1&sort=plate_number&order=desc&per_page=1&page=1')
            ->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('meta.last_page', 2)->assertJsonPath('data.0.plate_number', 'BA456');
        $this->getJson('/api/cars?search=Kia&category=B&is_active=1&sort=plate_number&order=desc&per_page=1&page=2')
            ->assertOk()->assertJsonPath('meta.current_page', 2)->assertJsonPath('data.0.plate_number', 'BA123');
        $this->getJson('/api/cars?sort=unknown')->assertUnprocessable()->assertJsonValidationErrors('sort');
    }

    public function test_admin_manages_questions_and_future_theory(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $question = $this->postJson('/api/questions', [
            'ticket_number' => 2, 'category' => 'B', 'text' => 'Что проверить перед началом движения?',
            'options' => ['Обстановку вокруг машины', 'Только расход топлива'], 'correct_option' => 0,
        ])->assertCreated()->assertJsonPath('data.correct_option', 0)->json('data.id');
        $this->patchJson("/api/questions/$question", ['correct_option' => 4])->assertUnprocessable();
        $this->patchJson("/api/questions/$question", ['correct_option' => 1])->assertOk()->assertJsonPath('data.correct_option', 1);
        $this->deleteJson("/api/questions/$question")->assertNoContent();

        $group = StudyGroup::factory()->create();
        $day = today()->addDays(2);
        $lesson = $this->postJson('/api/lessons', [
            'type' => 'theory', 'study_group_id' => $group->id, 'start_at' => $day->copy()->setHour(10)->toDateTimeString(),
            'end_at' => $day->copy()->setHour(11)->toDateTimeString(),
        ])->assertCreated()->json('data.id');
        $this->patchJson("/api/lessons/$lesson", ['end_at' => $day->copy()->setHour(12)->toDateTimeString()])
            ->assertOk()->assertJsonPath('data.minutes', 120);
        $this->deleteJson("/api/lessons/$lesson")->assertNoContent();
    }
}
