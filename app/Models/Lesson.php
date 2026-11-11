<?php

namespace App\Models;

use App\Enums\LessonStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lesson extends Model
{
    use HasFactory;

    public const RELATIONS = ['studyGroup', 'instructor', 'student', 'car'];

    protected $fillable = [
        'type', 'study_group_id', 'instructor_id', 'student_id',
        'car_id', 'start_at', 'end_at', 'status',
    ];

    protected function casts(): array
    {
        return ['start_at' => 'datetime', 'end_at' => 'datetime', 'status' => LessonStatus::class];
    }

    public function studyGroup(): BelongsTo
    {
        return $this->belongsTo(StudyGroup::class);
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    public function scopeOverlapping(Builder $query, $start, $end): Builder
    {
        return $query->where('start_at', '<', $end)
            ->where('end_at', '>', $start)
            ->where('status', '!=', LessonStatus::Cancelled->value);
    }

    public function durationMinutes(): int
    {
        return (int) $this->start_at->diffInMinutes($this->end_at);
    }
}
