<?php

namespace App\Models;

use App\Enums\LicenseCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    use HasFactory;

    protected $fillable = ['ticket_number', 'category', 'text', 'options', 'correct_option'];

    protected $hidden = ['correct_option'];

    protected function casts(): array
    {
        return ['category' => LicenseCategory::class, 'options' => 'array'];
    }
}
