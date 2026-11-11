<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ExamAttempt;
use App\Models\User;

class ExamAttemptPolicy
{
    public function view(User $user, ExamAttempt $attempt): bool
    {
        return $user->role === UserRole::Admin || $attempt->user_id === $user->id;
    }
}
