<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'student' => $this->whenLoaded('user', fn () => $this->user->only('id', 'name')),
            'amount' => $this->amount,
            'payment_date' => $this->payment_date->toDateString(),
            'payment_method' => $this->payment_method,
            'status' => $this->status,
            'comment' => $this->comment,
        ];
    }
}
