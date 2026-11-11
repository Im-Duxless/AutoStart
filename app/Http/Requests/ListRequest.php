<?php

namespace App\Http\Requests;

use App\Enums\EnrollmentStatus;
use App\Enums\LessonStatus;
use App\Enums\LicenseCategory;
use App\Enums\UserRole;
use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListRequest extends FormRequest
{
    public function paginate(EloquentBuilder|Builder $query, string $default = 'id', string $direction = 'desc', string $prefix = ''): LengthAwarePaginator
    {
        $sort = $this->validated('sort') ?? $default;
        $query->orderBy($prefix.$sort, $this->validated('order') ?? $direction);
        if ($sort !== 'id') {
            $query->orderBy($prefix.'id');
        }

        return $query->paginate($this->validated('per_page') ?? 12)->withQueryString();
    }

    public function rules(): array
    {
        $sortFields = match ($this->route()?->getName()) {
            'users.index' => ['name', 'email', 'role'],
            'study-groups.index' => ['name', 'category', 'start_date', 'capacity', 'tuition'],
            'cars.index' => ['brand', 'model', 'plate_number', 'category', 'is_active'],
            'lessons.index' => ['start_at', 'end_at', 'type', 'status'],
            'questions.index' => ['ticket_number', 'category', 'text'],
            'payments.index' => ['payment_date', 'amount', 'payment_method', 'status'],
            'exams.history' => ['category', 'ticket_number', 'correct_answers', 'created_at'],
            'enrollments.index' => ['status', 'created_at'],
            default => [],
        };

        $statuses = match ($this->route()?->getName()) {
            'study-groups.index' => ['open', 'in_progress', 'closed'],
            'lessons.index' => array_column(LessonStatus::cases(), 'value'),
            'enrollments.index' => array_column(EnrollmentStatus::cases(), 'value'),
            'payments.index' => Payment::STATUSES,
            default => [],
        };

        return [
            'search' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', Rule::enum(LicenseCategory::class)],
            'role' => ['nullable', Rule::enum(UserRole::class)],
            'status' => ['nullable', Rule::in($statuses)],
            'type' => ['nullable', Rule::in(['theory', 'driving'])],
            'mode' => ['nullable', Rule::in(['mine', 'available'])],
            'instructor_id' => ['nullable', 'integer', 'min:1'],
            'study_group_id' => ['nullable', 'integer', 'min:1'],
            'user_id' => ['nullable', 'integer', 'min:1'],
            'ticket_number' => ['nullable', 'integer', 'between:1,999'],
            'payment_method' => ['nullable', Rule::in(Payment::METHODS)],
            'is_active' => ['nullable', 'boolean'],
            'passed' => ['nullable', 'boolean'],
            'upcoming' => ['nullable', 'boolean'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', $this->filled('from') ? 'after_or_equal:from' : 'date'],
            'sort' => ['nullable', Rule::in(['id', ...$sortFields])],
            'order' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ];
    }
}
