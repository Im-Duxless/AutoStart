<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $fillable = ['name', 'email', 'password', 'role', 'phone'];

    protected $hidden = ['password', 'remember_token'];

    protected $attributes = ['role' => 'student'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function studyGroups(): BelongsToMany
    {
        return $this->belongsToMany(StudyGroup::class)
            ->withPivot('id', 'status')->withTimestamps();
    }

    public function approvedGroups(): BelongsToMany
    {
        return $this->studyGroups()->wherePivot('status', EnrollmentStatus::Approved->value);
    }

    public function drivingLessons(): HasMany
    {
        return $this->hasMany(Lesson::class, 'student_id');
    }

    public function instructedLessons(): HasMany
    {
        return $this->hasMany(Lesson::class, 'instructor_id');
    }

    public function examAttempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
