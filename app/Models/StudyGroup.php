<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Enums\LicenseCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudyGroup extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'category', 'capacity', 'status', 'start_date', 'tuition'];

    protected function casts(): array
    {
        return ['category' => LicenseCategory::class, 'start_date' => 'date', 'tuition' => 'decimal:2'];
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('id', 'status')->withTimestamps();
    }

    public function approvedStudents(): BelongsToMany
    {
        return $this->students()->wherePivot('status', EnrollmentStatus::Approved->value);
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }
}
