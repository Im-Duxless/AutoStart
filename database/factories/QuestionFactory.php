<?php

namespace Database\Factories;

use App\Enums\LicenseCategory;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Question> */
class QuestionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category' => LicenseCategory::B,
            'ticket_number' => 1,
            'text' => 'Что помогает сохранить запас времени для остановки?',
            'options' => ['Достаточная дистанция', 'Движение вплотную', 'Резкое перестроение'],
            'correct_option' => 0,
        ];
    }
}
