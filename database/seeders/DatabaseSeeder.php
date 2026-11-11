<?php

namespace Database\Seeders;

use App\Enums\LessonStatus;
use App\Models\Car;
use App\Models\Lesson;
use App\Models\Payment;
use App\Models\StudyGroup;
use App\Models\User;
use App\Services\ExamService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) || User::where('email', 'admin@autostart.local')->exists()) {
            return;
        }

        DB::transaction(function () {
            User::factory()->admin()->create(['name' => 'Администратор AutoStart', 'email' => 'admin@autostart.local']);
            $instructors = [
                User::factory()->instructor()->create(['name' => 'Артём Волков', 'email' => 'instructor@autostart.local']),
                User::factory()->instructor()->create(['name' => 'Марина Соколова', 'email' => 'marina@autostart.local']),
                User::factory()->instructor()->create(['name' => 'Алексей Орлов', 'email' => 'alexey@autostart.local']),
            ];
            $students = [
                User::factory()->create(['name' => 'Анна Смирнова', 'email' => 'student@autostart.local']),
                User::factory()->create(['name' => 'Максим Ким', 'email' => 'maxim@autostart.local']),
                User::factory()->create(['name' => 'Диана Исмаилова', 'email' => 'diana@autostart.local']),
                User::factory()->create(['name' => 'Денис Котов', 'email' => 'denis@autostart.local']),
            ];

            $groups = $this->groups();
            $cars = $this->cars();

            $groups[0]->students()->attach($students[0], ['status' => 'approved']);
            $groups[0]->students()->attach($students[1], ['status' => 'approved']);
            $groups[1]->students()->attach($students[2], ['status' => 'pending']);
            $groups[4]->students()->attach($students[3], ['status' => 'pending']);

            $this->lessons($groups, $cars, $instructors, $students[0]);
            $this->payments($students);
            $this->call(QuestionSeeder::class);
            $this->attempts($students);
        });
    }

    private function groups(): array
    {
        $groups = [
            ['В-11 · Вечерняя', 'B', 'in_progress', -7, '125000.00'],
            ['В-12 · Утренняя', 'B', 'open', 5, '125000.00'],
            ['В-13 · Выходного дня', 'B', 'open', 10, '135000.00'],
            ['А-04 · Утренняя', 'A', 'open', 7, '85000.00'],
            ['С-05 · Дневная', 'C', 'open', 12, '165000.00'],
            ['С-04 · Дневная', 'C', 'closed', -25, '155000.00'],
        ];

        return array_map(fn ($group) => StudyGroup::create([
            'name' => $group[0], 'category' => $group[1], 'status' => $group[2],
            'start_date' => today()->addDays($group[3]), 'tuition' => $group[4], 'capacity' => 12,
        ]), $groups);
    }

    private function cars(): array
    {
        $cars = [
            ['Hyundai', 'Accent', '101AS01', 'B', true],
            ['Kia', 'Rio', '102AS01', 'B', true],
            ['Yamaha', 'MT-03', '201AS01', 'A', true],
            ['Isuzu', 'NPR', '301AS01', 'C', true],
            ['ГАЗ', 'ГАЗон Next', '302AS01', 'C', false],
            ['Škoda', 'Rapid', '103AS01', 'B', true],
        ];

        return array_map(fn ($car) => Car::create([
            'brand' => $car[0], 'model' => $car[1], 'plate_number' => $car[2],
            'category' => $car[3], 'is_active' => $car[4],
        ]), $cars);
    }

    private function lessons(array $groups, array $cars, array $instructors, User $student): void
    {
        foreach ([[0, 1, 19], [0, 3, 19], [1, 5, 10], [2, 10, 12], [3, 7, 11], [4, 12, 14]] as [$group, $day, $hour]) {
            $start = today()->addDays($day)->setHour($hour);
            Lesson::create([
                'type' => 'theory', 'study_group_id' => $groups[$group]->id,
                'status' => LessonStatus::Scheduled, 'start_at' => $start, 'end_at' => $start->copy()->addMinutes(90),
            ]);
        }
        foreach ([1, 2, 3, 5, 7] as $day) {
            $this->driving($instructors[0], $cars[0], $day, 10, LessonStatus::Free);
            $this->driving($instructors[1], $cars[1], $day, 14, LessonStatus::Free);
            $this->driving($instructors[1], $cars[2], $day, 16, LessonStatus::Free);
            $this->driving($instructors[2], $cars[3], $day, 11, LessonStatus::Free);
        }
        $this->driving($instructors[0], $cars[0], -3, 10, LessonStatus::Completed, $student);
        $this->driving($instructors[0], $cars[0], -1, 10, LessonStatus::Booked, $student);
        $this->driving($instructors[1], $cars[1], 2, 17, LessonStatus::Booked, $student);
    }

    private function driving(User $instructor, Car $car, int $day, int $hour, LessonStatus $status, ?User $student = null): void
    {
        $start = today()->addDays($day)->setHour($hour);
        Lesson::create([
            'type' => 'driving', 'instructor_id' => $instructor->id, 'car_id' => $car->id,
            'student_id' => $student?->id, 'status' => $status,
            'start_at' => $start, 'end_at' => $start->copy()->addMinutes(90),
        ]);
    }

    private function payments(array $students): void
    {
        foreach ([[0, '45000.00', 6, 'transfer'], [0, '20000.00', 3, 'cash'], [1, '50000.00', 5, 'card'],
            [1, '15000.00', 2, 'cash'], [0, '10000.00', 1, 'card']] as [$student, $amount, $days, $method]) {
            Payment::create([
                'user_id' => $students[$student]->id, 'amount' => $amount, 'payment_date' => today()->subDays($days),
                'payment_method' => $method, 'status' => 'received', 'comment' => 'Взнос за обучение',
            ]);
        }
    }

    private function attempts(array $students): void
    {
        $exams = app(ExamService::class);
        foreach ([[0, 1, 'B', 0], [0, 2, 'B', 2], [1, 1, 'B', 1], [2, 1, 'A', 3], [3, 1, 'C', 0]] as [$student, $ticket, $category, $errors]) {
            $questions = $exams->questions($ticket, $category);
            $answers = $questions->values()->map(fn ($question, $index) => [
                'question_id' => $question->id,
                'option' => ($question->correct_option + (int) ($index < $errors)) % count($question->options),
            ])->all();
            $exams->submit($students[$student], [
                'ticket_number' => $ticket, 'category' => $category, 'version' => $exams->version($questions),
                'answers' => $answers,
            ]);
        }
    }
}
