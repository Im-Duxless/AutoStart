<?php

namespace App\Models;

use App\Enums\LicenseCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'ticket_number', 'category', 'answers', 'total_questions', 'correct_answers', 'passed',
    ];

    protected function casts(): array
    {
        return ['category' => LicenseCategory::class, 'answers' => 'array', 'passed' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
