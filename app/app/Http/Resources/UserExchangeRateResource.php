<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserExchangeRateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'from_currency_id' => $this->from_currency_id,
            'to_currency_id' => $this->to_currency_id,
            'from_currency' => new CurrencyResource($this->whenLoaded('fromCurrency')),
            'to_currency' => new CurrencyResource($this->whenLoaded('toCurrency')),
            'exchange_rate' => $this->exchange_rate,
            'effective_date' => $this->effective_date->format('Y-m-d'),
            'cease_date' => $this->cease_date?->format('Y-m-d'),
            'is_default' => $this->is_default,
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
