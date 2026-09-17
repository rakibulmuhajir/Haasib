<?php

namespace App\Modules\FuelStation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Services\CustomerStatementService;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $openBalances = \App\Modules\Accounting\Models\Invoice::where('company_id', $company->id)
            ->whereNotIn('status', ['void', 'draft'])
            ->where('balance', '>', 0)
            ->selectRaw('customer_id, SUM(balance) as balance')
            ->groupBy('customer_id')
            ->pluck('balance', 'customer_id');

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
                'current_balance' => (float) ($openBalances[$c->id] ?? 0),
                'is_credit_blocked' => (bool) $c->is_credit_blocked,
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

        // Built from the canonical AR-facing models (invoices, payments, credit notes), not
        // from daily-close metadata, so a standalone invoice or payment that never touched a
        // close still appears here (see CustomerStatementService for why the close's own
        // consolidated journal cannot itself carry a per-customer balance).
        $statement = app(CustomerStatementService::class)->statement($customerData);
        $openBalance = $statement['closing_balance'];

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
                'current_balance' => $openBalance,
                'is_credit_blocked' => (bool) $customerData->is_credit_blocked,
            ],
            'statement' => $statement['rows'],
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
     * Toggle credit block status. A blocked buyer is refused a new credit sale at every
     * entry point (see DailyCloseCreditSaleService::prepare and FuelSaleService::createSale);
     * it never touches existing invoices or payments.
     */
    public function toggleBlock(Request $request, string $company, string $customer): RedirectResponse
    {
        $companyModel = app(CurrentCompany::class)->get();

        $customerModel = Customer::where('company_id', $companyModel->id)->findOrFail($customer);
        $customerModel->update(['is_credit_blocked' => ! $customerModel->is_credit_blocked]);

        return redirect()->back()->with('success', $customerModel->is_credit_blocked ? 'Credit blocked.' : 'Credit unblocked.');
    }
}
