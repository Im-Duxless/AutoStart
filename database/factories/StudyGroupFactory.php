<?php

namespace Database\Factories;

use App\Enums\LicenseCategory;
use App\Models\StudyGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StudyGroup> */
class StudyGroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Группа '.fake()->unique()->numberBetween(100, 9999),
            'category' => LicenseCategory::B,
            'capacity' => 12,
            'status' => 'open',
            'start_date' => today()->addDays(7),
            'tuition' => '125000.00',
        ];
    }
}
