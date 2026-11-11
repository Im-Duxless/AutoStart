<?php

namespace App\Policies;

use App\Enums\LessonStatus;
use App\Enums\UserRole;
use App\Models\Lesson;
use App\Models\User;

class LessonPolicy
{
    public function before(User $user): ?bool
    {
        return $user->role === UserRole::Admin ? true : null;
    }

    public function view(User $user, Lesson $lesson): bool
    {
        if ($user->role === UserRole::Instructor) {
            return $lesson->instructor_id === $user->id;
        }
        if ($lesson->type === 'theory') {
            return $user->approvedGroups()->whereKey($lesson->study_group_id)->exists();
        }

        return $lesson->student_id === $user->id || (
            $lesson->status === LessonStatus::Free && $lesson->start_at->isFuture()
            && $lesson->car?->is_active && $lesson->instructor?->role === UserRole::Instructor
            && $user->approvedGroups()->where('category', $lesson->car->category->value)->exists()
        );
    }

    public function update(User $user, Lesson $lesson): bool
    {
        return $user->role === UserRole::Instructor && $lesson->instructor_id === $user->id;
    }

    public function cancel(User $user, Lesson $lesson): bool
    {
        return $this->update($user, $lesson)
            || ($user->role === UserRole::Student && $lesson->student_id === $user->id);
    }

    public function complete(User $user, Lesson $lesson): bool
    {
        return $this->update($user, $lesson);
    }

    public function delete(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }
}
