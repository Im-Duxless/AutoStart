<?php

namespace App\Models;

use App\Enums\LicenseCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Car extends Model
{
    use HasFactory;

    protected $fillable = ['brand', 'model', 'plate_number', 'category', 'is_active'];

    protected function casts(): array
    {
        return ['category' => LicenseCategory::class, 'is_active' => 'boolean'];
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }
}
