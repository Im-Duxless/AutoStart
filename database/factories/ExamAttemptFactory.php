<?php

namespace Database\Factories;

use App\Enums\LicenseCategory;
use App\Models\ExamAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExamAttempt> */
class ExamAttemptFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category' => LicenseCategory::B,
            'ticket_number' => 1,
            'answers' => [[
                'question_id' => 1,
                'text' => 'Что нужно сделать перед перестроением?',
                'options' => ['Проверить обстановку и подать сигнал', 'Ускориться без сигнала'],
                'selected_option' => 0,
                'correct_option' => 0,
            ]],
            'total_questions' => 1,
            'correct_answers' => 1,
            'passed' => true,
        ];
    }
}
