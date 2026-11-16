<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DemoPaymentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'decimal:0,2', 'between:0.01,99999999.99'],
            'payment_method' => ['required', Rule::in(['cash', 'card'])],
            'reference' => ['required', 'uuid:4'],
        ];
    }
}
