<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    public const METHODS = ['cash', 'transfer', 'card'];

    public const MANUAL_STATUSES = ['received', 'void'];
    public const STATUSES = ['pending', ...self::MANUAL_STATUSES];

    protected $fillable = ['user_id', 'amount', 'payment_date', 'payment_method', 'status', 'comment', 'demo_reference'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'payment_date' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
