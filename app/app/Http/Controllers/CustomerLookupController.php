<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerLookupController extends Controller
{
    public function suggest(Request $request)
    {
        $user = $request->user();
        $q = (string) $request->query('q', '');
        $companyId = $request->query('company_id');
        $limit = (int) $request->query('limit', 8);

        $query = Customer::query();

        if ($q !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
            $query->where(function ($w) use ($like) {
                $w->where('name', 'ilike', $like)
                    ->orWhere('email', 'ilike', $like)
                    ->orWhere('customer_number', 'ilike', $like);
            });
        }

        // Non-superadmin: restrict to current company
        if (! $user->isSuperAdmin()) {
            $companyId = $companyId ?: $request->session()->get('current_company_id');
            abort_if(! $companyId, 422, 'Company context required');
            $query->where('company_id', $companyId);
        } elseif ($companyId) {
            // Superadmin with specific company filter
            $query->where('company_id', $companyId);
        }

        $customers = $query->where('is_active', true)
            ->limit($limit)
            ->get(['customer_id', 'name', 'email', 'customer_number']);

        return response()->json(['data' => $customers]);
    }

    public function show(Request $request, string $customer)
    {
        $actor = $request->user();

        $customer = Customer::query()
            ->when(str_contains($customer, '@'),
                fn ($q) => $q->where('email', $customer),
                fn ($q) => $q->where('customer_id', $customer)
            )
            ->firstOrFail();

        // Check permissions
        if (! $actor->isSuperAdmin()) {
            $companyId = $request->session()->get('current_company_id');
            abort_unless($companyId === $customer->company_id, 403);
        }

        return response()->json([
            'data' => [
                'id' => $customer->customer_id,
                'name' => $customer->name,
                'email' => $customer->email,
                'customer_number' => $customer->customer_number,
                'phone' => $customer->phone,
                'is_active' => $customer->is_active,
                'created_at' => $customer->created_at,
            ],
        ]);
    }
}
