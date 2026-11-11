<?php

namespace Database\Factories;

use App\Enums\LessonStatus;
use App\Models\Car;
use App\Models\Lesson;
use App\Models\StudyGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Lesson> */
class LessonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => 'theory',
            'study_group_id' => StudyGroup::factory(),
            'start_at' => today()->addDay()->setHour(10),
            'end_at' => today()->addDay()->setHour(11),
            'status' => LessonStatus::Scheduled,
        ];
    }

    public function driving(): static
    {
        return $this->state([
            'type' => 'driving',
            'study_group_id' => null,
            'car_id' => Car::factory(),
            'instructor_id' => User::factory()->instructor(),
            'status' => LessonStatus::Free,
        ]);
    }
}
