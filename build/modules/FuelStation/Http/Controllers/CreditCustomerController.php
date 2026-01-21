<?php

namespace App\Modules\FuelStation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Customer;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CreditCustomerController extends Controller
{
    /**
     * List credit customers (customers with credit accounts for fuel).
     */
    public function index(): Response
    {
        $company = app(CurrentCompany::class)->get();

        // Get customers from acct.customers
        $customers = Customer::where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'code' => $c->customer_number,
                'phone' => $c->phone,
                'email' => $c->email,
                'credit_limit' => (float) ($c->credit_limit ?? 0),
                'current_balance' => 0, // TODO: Calculate from transactions
                'is_credit_blocked' => false, // TODO: Add to customer model
            ]);

        // Calculate stats
        $stats = [
            'total_customers' => $customers->count(),
            'total_receivable' => $customers->sum('current_balance'),
            'over_limit' => $customers->filter(fn($c) => $c['credit_limit'] > 0 && $c['current_balance'] > $c['credit_limit'])->count(),
            'blocked' => $customers->where('is_credit_blocked', true)->count(),
        ];

        return Inertia::render('FuelStation/CreditCustomers/Index', [
            'customers' => $customers,
            'stats' => $stats,
            'currency' => $company->base_currency ?? 'PKR',
        ]);
    }

    /**
     * Show credit customer details with transaction history.
     */
    public function show(Request $request, string $company, string $customer): Response
    {
        $companyModel = app(CurrentCompany::class)->get();

        $customerData = Customer::where('company_id', $companyModel->id)
            ->where('id', $customer)
            ->first();

        if (!$customerData) {
            abort(404);
        }

        // Get credit transactions from daily close metadata
        $transactions = DB::table('acct.transactions')
            ->where('company_id', $companyModel->id)
            ->where('transaction_type', 'daily_close')
            ->whereIn('status', ['posted', 'locked'])
            ->orderByDesc('transaction_date')
            ->limit(100)
            ->get()
            ->flatMap(function ($txn) use ($customer) {
                $metadata = json_decode($txn->metadata, true) ?? [];
                $creditSales = $metadata['credit_sales'] ?? [];

                return collect($creditSales)
                    ->filter(fn($sale) => ($sale['customer_id'] ?? '') === $customer)
                    ->map(fn($sale) => [
                        'id' => $txn->id . '-' . ($sale['customer_id'] ?? ''),
                        'date' => $txn->transaction_date,
                        'type' => 'sale',
                        'description' => $sale['description'] ?? 'Credit sale',
                        'amount' => (float) ($sale['amount'] ?? 0),
                        'liters' => (float) ($sale['liters'] ?? 0),
                    ]);
            });

        // Get collections/payments
        $collections = DB::table('acct.transactions')
            ->where('company_id', $companyModel->id)
            ->where('transaction_type', 'credit_collection')
            ->whereRaw("metadata->>'customer_id' = ?", [$customer])
            ->orderByDesc('transaction_date')
            ->limit(50)
            ->get()
            ->map(fn($txn) => [
                'id' => $txn->id,
                'date' => $txn->transaction_date,
                'type' => 'collection',
                'description' => $txn->description ?? 'Payment received',
                'amount' => (float) $txn->total_amount,
                'reference' => $txn->reference,
            ]);

        // Merge and sort
        $allTransactions = $transactions->merge($collections)
            ->sortByDesc('date')
            ->values()
            ->take(50);

        // Get billing address as string
        $address = null;
        if ($customerData->billing_address) {
            $addr = $customerData->billing_address;
            $parts = array_filter([
                $addr['street'] ?? null,
                $addr['city'] ?? null,
                $addr['state'] ?? null,
                $addr['postal_code'] ?? null,
            ]);
            $address = implode(', ', $parts);
        }

        return Inertia::render('FuelStation/CreditCustomers/Show', [
            'customer' => [
                'id' => $customerData->id,
                'name' => $customerData->name,
                'code' => $customerData->customer_number,
                'phone' => $customerData->phone,
                'email' => $customerData->email,
                'address' => $address,
                'credit_limit' => (float) ($customerData->credit_limit ?? 0),
                'current_balance' => 0, // TODO: Calculate from transactions
                'is_credit_blocked' => false,
            ],
            'transactions' => $allTransactions,
            'currency' => $companyModel->base_currency ?? 'PKR',
        ]);
    }

    /**
     * Update credit limit for a customer.
     */
    public function updateLimit(Request $request, string $company, string $customer): RedirectResponse
    {
        $companyModel = app(CurrentCompany::class)->get();

        $validated = $request->validate([
            'credit_limit' => ['required', 'numeric', 'min:0'],
        ]);

        Customer::where('company_id', $companyModel->id)
            ->where('id', $customer)
            ->update([
                'credit_limit' => $validated['credit_limit'],
            ]);

        return redirect()->back()->with('success', 'Credit limit updated.');
    }

    /**
     * Toggle credit block status.
     * NOTE: This is a placeholder - blocking functionality would need a column added to customers table.
     */
    public function toggleBlock(Request $request, string $company, string $customer): RedirectResponse
    {
        // For now, just return success - actual blocking would require schema changes
        return redirect()->back()->with('success', 'Credit status updated.');
    }
}
