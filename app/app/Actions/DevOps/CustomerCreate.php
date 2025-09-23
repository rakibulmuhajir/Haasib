<?php

namespace App\Actions\DevOps;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CustomerCreate
{
    public function handle(array $p, User $actor): array
    {
        abort_unless($actor->isSuperAdmin(), 403);

        $data = Validator::make($p, [
            'company_id' => 'required|uuid|exists:auth.companies,id',
            'name' => 'required|string|max:255',
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
            'idempotency_key' => 'nullable|string|max:255',
        ])->validate();

        $customer = DB::transaction(function () use ($data, $actor) {
            $customerData = [
                'customer_id' => $data['idempotency_key'] ?
                    Customer::generateIdFromIdempotencyKey($data['idempotency_key']) :
                    (string) Str::uuid(),
                'company_id' => $data['company_id'],
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'tax_number' => $data['tax_number'] ?? null,
                'billing_address' => $data['billing_address'] ?? null,
                'shipping_address' => $data['shipping_address'] ?? null,
                'currency_id' => $data['currency_id'] ?? null,
                'payment_terms' => $data['payment_terms'] ?? 0,
                'credit_limit' => $data['credit_limit'] ?? null,
                'notes' => $data['notes'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'customer_number' => Customer::generateNextNumber($data['company_id']),
                'created_by_user_id' => $actor->id,
            ];

            if ($data['idempotency_key']) {
                $customerData['idempotency_key'] = $data['idempotency_key'];
            }

            return Customer::create($customerData);
        });

        return [
            'message' => 'Customer created',
            'data' => [
                'id' => $customer->customer_id,
                'name' => $customer->name,
                'email' => $customer->email,
                'customer_number' => $customer->customer_number,
            ],
        ];
    }
}
