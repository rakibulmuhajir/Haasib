<?php

namespace App\Actions\DevOps;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class CustomerUpdate
{
    public function handle(array $p, User $actor): array
    {
        abort_unless($actor->isSuperAdmin(), 403);

        $data = Validator::make($p, [
            'id' => 'required|string|exists:customers,customer_id',
            'name' => 'sometimes|required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'tax_number' => 'nullable|string|max:100',
            'billing_address' => 'nullable|array',
            'shipping_address' => 'nullable|array',
            'currency_id' => 'nullable|string|max:3',
            'payment_terms' => 'nullable|integer|min:0|max:365',
            'credit_limit' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ])->validate();

        $customer = Customer::findOrFail($data['id']);

        $customer->update(array_filter($data, function ($value, $key) {
            return in_array($key, [
                'name', 'email', 'phone', 'tax_number', 'billing_address',
                'shipping_address', 'currency_id', 'payment_terms',
                'credit_limit', 'notes', 'is_active',
            ]);
        }, ARRAY_FILTER_USE_BOTH));

        return [
            'message' => 'Customer updated',
            'data' => [
                'id' => $customer->customer_id,
                'name' => $customer->name,
                'email' => $customer->email,
                'is_active' => $customer->is_active,
            ],
        ];
    }
}
