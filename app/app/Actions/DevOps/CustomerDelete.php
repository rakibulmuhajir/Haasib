<?php

namespace App\Actions\DevOps;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CustomerDelete
{
    public function handle(array $p, User $actor): array
    {
        abort_unless($actor->isSuperAdmin(), 403);

        $customer = Customer::findOrFail($p['id']);

        DB::transaction(function () use ($customer) {
            $customer->delete();
        });

        return [
            'message' => 'Customer deleted',
            'data' => [
                'id' => $customer->customer_id,
                'name' => $customer->name,
            ],
        ];
    }
}
