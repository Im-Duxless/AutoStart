<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category->value,
            'ticket_number' => $this->ticket_number,
            'text' => $this->text,
            'options' => $this->options,
            'correct_option' => $this->when($request->routeIs('questions.*'), fn () => (int) $this->correct_option),
        ];
    }
}
