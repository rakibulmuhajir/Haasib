<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->customer_id,
            'customer_number' => $this->customer_number,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'customer_type' => $this->customer_type,
            'status' => $this->status,
            'tax_number' => $this->tax_number,
            'is_active' => $this->is_active,
            'billing_address' => $this->billing_address,
            'shipping_address' => $this->shipping_address,
            'website' => $this->website,
            'credit_limit' => $this->credit_limit,
            'payment_terms' => $this->payment_terms,
            'notes' => $this->notes,
            'tax_exempt' => $this->tax_exempt,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Computed attributes
            'outstanding_balance' => $this->getOutstandingBalance(),
            'risk_level' => $this->getRiskLevel(),
            
            // Relationships
            'currency' => $this->whenLoaded('currency', fn () => new CurrencyResource($this->currency)),
            'country' => $this->whenLoaded('country_relation', function() {
                $countryId = $this->billing_address['country_id'] ?? null;
                return $countryId && $this->country_relation ? new CountryResource($this->country_relation) : null;
            }),
        ];
    }
}