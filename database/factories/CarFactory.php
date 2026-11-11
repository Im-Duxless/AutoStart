<?php

namespace Database\Factories;

use App\Enums\LicenseCategory;
use App\Models\Car;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Car> */
class CarFactory extends Factory
{
    public function definition(): array
    {
        return [
            'brand' => 'Hyundai',
            'model' => 'Accent',
            'plate_number' => fake()->unique()->bothify('###AS##'),
            'category' => LicenseCategory::B,
            'is_active' => true,
        ];
    }
}
