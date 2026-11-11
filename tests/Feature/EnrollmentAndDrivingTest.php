<?php

namespace Tests\Feature;

use App\Enums\LessonStatus;
use App\Models\Car;
use App\Models\Lesson;
use App\Models\StudyGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EnrollmentAndDrivingTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_booking_completion_and_report_work_together(): void
    {
        $this->travelTo(today()->setHour(9));
        $admin = User::factory()->admin()->create();
        $instructor = User::factory()->instructor()->create();
        $outsider = User::factory()->instructor()->create();
        $student = User::factory()->create();
        $second = User::factory()->create();
        $group = StudyGroup::factory()->create(['start_date' => today()]);
        $car = Car::factory()->create();
        $group->students()->attach($second, ['status' => 'approved']);

        Sanctum::actingAs($admin);
        $this->postJson('/api/lessons', [
            'type' => 'theory', 'study_group_id' => $group->id,
            'start_at' => today()->addDay()->setHour(10)->toDateTimeString(),
            'end_at' => today()->addDay()->setHour(11)->toDateTimeString(),
        ])->assertCreated()->assertJsonPath('data.status', 'scheduled');

        Sanctum::actingAs($instructor);
        $start = today()->addDay()->setHour(13);
        $end = $start->copy()->addMinutes(90);
        $slot = $this->postJson('/api/lessons', [
            'type' => 'driving', 'car_id' => $car->id, 'start_at' => $start->toDateTimeString(), 'end_at' => $end->toDateTimeString(),
        ])->assertCreated()->assertJsonPath('data.instructor_id', $instructor->id)->json('data.id');
        $this->postJson("/api/lessons/$slot/complete")->assertConflict();

        Sanctum::actingAs($student);
        $this->getJson('/api/lessons?type=theory')->assertJsonCount(0, 'data');
        $this->postJson("/api/lessons/$slot/book")->assertConflict();
        $application = $this->postJson("/api/study-groups/{$group->id}/enroll")->assertCreated()
            ->assertJsonPath('data.status', 'pending')->json('data.id');
        $this->postJson("/api/study-groups/{$group->id}/enroll")->assertConflict();
        $this->patchJson("/api/enrollments/$application", ['status' => 'approved'])->assertForbidden();

        Sanctum::actingAs($admin);
        $this->patchJson("/api/enrollments/$application", ['status' => 'approved'])->assertOk()->assertJsonPath('data.status', 'approved');

        Sanctum::actingAs($student);
        $this->getJson('/api/lessons?type=theory')->assertJsonCount(1, 'data');
        $this->getJson('/api/lessons?mode=available')->assertJsonPath('data.0.id', $slot);
        $this->postJson("/api/lessons/$slot/book")->assertOk()->assertJsonPath('data.student_id', $student->id);

        Sanctum::actingAs($second);
        $this->postJson("/api/lessons/$slot/book")->assertConflict();
        $this->getJson("/api/lessons/$slot")->assertForbidden();
        $this->postJson("/api/lessons/$slot/cancel")->assertForbidden();
        $this->getJson('/api/lessons?mode=mine&type=driving')->assertJsonCount(0, 'data');

        Sanctum::actingAs($outsider);
        $this->getJson('/api/lessons')->assertJsonCount(0, 'data');
        $this->postJson("/api/lessons/$slot/complete")->assertForbidden();

        Sanctum::actingAs($instructor);
        $this->postJson("/api/lessons/$slot/complete")->assertConflict();
        $this->travelTo($end->copy()->addMinute());
        $this->postJson("/api/lessons/$slot/complete")->assertOk()->assertJsonPath('data.status', 'completed');
        $this->postJson("/api/lessons/$slot/complete")->assertConflict();
        $this->getJson('/api/lessons/summary')->assertJsonPath('data.minutes', 90);

        Sanctum::actingAs($student);
        $this->getJson('/api/lessons/summary')->assertJsonPath('data.hours', 1.5);

        Sanctum::actingAs($admin);
        $day = $start->toDateString();
        $this->getJson("/api/reports/instructors?from=$day&to=$day")
            ->assertOk()->assertJsonFragment(['id' => $instructor->id, 'minutes' => 90, 'completed' => 1]);
    }

    public function test_approval_respects_capacity_and_closed_groups(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $group = StudyGroup::factory()->create(['capacity' => 1]);
        $closed = StudyGroup::factory()->create(['status' => 'closed']);
        Sanctum::actingAs($first);
        $a = $this->postJson("/api/study-groups/{$group->id}/enroll")->assertCreated()->json('data.id');
        $this->postJson("/api/study-groups/{$closed->id}/enroll")->assertConflict();
        Sanctum::actingAs($second);
        $b = $this->postJson("/api/study-groups/{$group->id}/enroll")->assertCreated()->json('data.id');

        Sanctum::actingAs(User::factory()->admin()->create());
        $this->patchJson("/api/enrollments/$a", ['status' => 'approved'])->assertOk();
        $this->patchJson("/api/enrollments/$b", ['status' => 'approved'])->assertConflict();
        $this->assertDatabaseHas('study_group_user', ['id' => $b, 'status' => 'pending']);
        $this->patchJson("/api/enrollments/$b", ['status' => 'rejected'])->assertOk();
        $this->patchJson("/api/enrollments/$b", ['status' => 'approved'])->assertConflict();
        $this->assertSame(1, $group->approvedStudents()->count());
    }

    public function test_slots_check_time_instructor_car_and_student_overlap(): void
    {
        $teacher = User::factory()->instructor()->create();
        $other = User::factory()->instructor()->create();
        $car = Car::factory()->create();
        $secondCar = Car::factory()->create();
        $start = today()->addDay()->setHour(10);
        $end = $start->copy()->addHour();
        $payload = ['type' => 'driving', 'car_id' => $car->id, 'start_at' => $start->toDateTimeString(), 'end_at' => $end->toDateTimeString()];
        Sanctum::actingAs($teacher);
        $this->postJson('/api/lessons', array_replace($payload, ['end_at' => $start->toDateTimeString()]))
            ->assertUnprocessable()->assertJsonValidationErrors('end_at');
        $this->postJson('/api/lessons', array_replace($payload, ['start_at' => today()->subDay()->toDateTimeString()]))->assertConflict();
        $this->postJson('/api/lessons', $payload + ['instructor_id' => $other->id])->assertForbidden();
        $slot = $this->postJson('/api/lessons', $payload)->assertCreated()->json('data.id');
        $this->postJson('/api/lessons', array_replace($payload, ['car_id' => $secondCar->id]))->assertConflict();

        Sanctum::actingAs($other);
        $this->postJson('/api/lessons', $payload)->assertConflict();
        $second = $this->postJson('/api/lessons', array_replace($payload, ['car_id' => $secondCar->id]))->assertCreated()->json('data.id');
        $inactive = Car::factory()->create(['is_active' => false]);
        $this->postJson('/api/lessons', array_replace($payload, ['car_id' => $inactive->id]))->assertConflict();

        $student = User::factory()->create();
        $group = StudyGroup::factory()->create();
        $group->students()->attach($student, ['status' => 'approved']);
        Sanctum::actingAs($student);
        $this->postJson("/api/lessons/$slot/book")->assertOk();
        $this->postJson("/api/lessons/$second/book")->assertConflict();
        $this->postJson("/api/lessons/$slot/cancel")->assertOk()
            ->assertJsonPath('data.status', 'free')->assertJsonPath('data.student_id', null);

        Sanctum::actingAs(User::factory()->admin()->create());
        $this->postJson('/api/lessons', [
            'type' => 'theory', 'study_group_id' => $group->id, 'start_at' => $start->toDateTimeString(), 'end_at' => $end->toDateTimeString(),
        ])->assertCreated();

        Sanctum::actingAs($student);
        $this->postJson("/api/lessons/$second/book")->assertConflict();
        $this->assertSame(LessonStatus::Free, Lesson::findOrFail($second)->status);

        $wrongCategory = Lesson::factory()->driving()->create([
            'car_id' => Car::factory()->create(['category' => 'C'])->id,
            'start_at' => $end->copy(), 'end_at' => $end->copy()->addHour(),
        ]);
        $this->postJson("/api/lessons/{$wrongCategory->id}/book")->assertConflict();
        $this->getJson("/api/lessons/{$wrongCategory->id}")->assertForbidden();
        $this->travelTo($end->copy()->addHours(2));
        $this->postJson("/api/lessons/$slot/book")->assertConflict();
    }
}
