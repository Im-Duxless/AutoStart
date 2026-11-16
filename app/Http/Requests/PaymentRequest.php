<?php

namespace App\Http\Requests;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentRequest extends FormRequest
{
    public function rules(): array
    {
        $presence = $this->route('payment') ? 'sometimes' : 'required';

        return [
            'user_id' => [$presence, 'required', 'integer', 'exists:users,id'],
            'amount' => [$presence, 'required', 'numeric', 'decimal:0,2', 'between:0.01,99999999.99'],
            'payment_date' => [$presence, 'required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'payment_method' => [$presence, 'required', Rule::in(Payment::METHODS)],
            'status' => ['sometimes', 'required', Rule::in(Payment::MANUAL_STATUSES)],
            'comment' => ['nullable', 'string', 'max:500'],
        ];
    }
}
