<?php

namespace App\Modules\FuelStation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Http\Requests\ApplyPaymentCreditRequest;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Services\CustomerStatementService;
use App\Modules\FuelStation\Http\Requests\UpdateCustomerFuelDiscountsRequest;
use App\Modules\FuelStation\Models\CustomerFuelDiscount;
use App\Modules\Inventory\Models\Item;
use App\Services\CommandBus;
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

        // A short "3/L diesel" / "5% petrol" chip per customer, for the list -- the full
        // editable card lives on Show.vue.
        $discountChips = CustomerFuelDiscount::where('company_id', $company->id)
            ->with('item:id,name,fuel_category')
            ->get()
            ->groupBy('customer_id')
            ->map(fn ($rows) => $rows->map(fn (CustomerFuelDiscount $d) => $this->formatDiscountChip($d))->values());

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
                'discount_chips' => $discountChips[$c->id] ?? [],
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

        $openInvoices = app(\App\Modules\Accounting\Services\PaymentAllocationService::class)
            ->openInvoicesOldestFirst($companyModel->id, $customerData->id)
            ->map(fn ($invoice) => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'balance' => (float) $invoice->balance,
                'currency' => $invoice->currency,
            ])->values();

        // Every active fuel item, each with this customer's discount if one is set --
        // the Discounts card lists "None" for every item that has none.
        $fuelItems = Item::where('company_id', $companyModel->id)
            ->whereNotNull('fuel_category')
            ->where('is_active', true)
            ->orderBy('fuel_category')
            ->get(['id', 'name', 'fuel_category']);
        $discountsByItem = CustomerFuelDiscount::where('company_id', $companyModel->id)
            ->where('customer_id', $customerData->id)
            ->get()
            ->keyBy('item_id');
        $discounts = $fuelItems->map(fn (Item $item) => [
            'item_id' => $item->id,
            'item_name' => $item->name,
            'fuel_category' => $item->fuel_category,
            'discount_type' => $discountsByItem[$item->id]->discount_type ?? null,
            'value' => $discountsByItem[$item->id] ? (float) $discountsByItem[$item->id]->value : null,
        ])->values();

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
                'available_credit' => $statement['available_credit'],
                'is_credit_blocked' => (bool) $customerData->is_credit_blocked,
            ],
            'statement' => $statement['rows'],
            'openInvoices' => $openInvoices,
            'discounts' => $discounts,
            'currency' => $companyModel->base_currency ?? 'PKR',
        ]);
    }

    /**
     * Save this customer's per-fuel-item discounts. A row with no discount_type clears
     * whatever was set for that item; every discount's maths still goes through
     * CustomerFuelDiscountService at sale time -- this only stores the rate.
     */
    public function updateDiscounts(UpdateCustomerFuelDiscountsRequest $request, string $company, string $customer): RedirectResponse
    {
        $companyModel = app(CurrentCompany::class)->get();
        $customerModel = Customer::where('company_id', $companyModel->id)->findOrFail($customer);

        foreach ($request->validated('discounts', []) as $row) {
            if (empty($row['discount_type'])) {
                CustomerFuelDiscount::where('company_id', $companyModel->id)
                    ->where('customer_id', $customerModel->id)
                    ->where('item_id', $row['item_id'])
                    ->delete();
                continue;
            }

            CustomerFuelDiscount::updateOrCreate(
                ['company_id' => $companyModel->id, 'customer_id' => $customerModel->id, 'item_id' => $row['item_id']],
                ['discount_type' => $row['discount_type'], 'value' => $row['value']]
            );
        }

        return redirect()->back()->with('success', 'Fuel discounts updated.');
    }

    private function formatDiscountChip(CustomerFuelDiscount $discount): array
    {
        $item = $discount->item;
        $label = $discount->discount_type === CustomerFuelDiscount::TYPE_PERCENT
            ? rtrim(rtrim(number_format((float) $discount->value, 2), '0'), '.') . '%'
            : rtrim(rtrim(number_format((float) $discount->value, 2), '0'), '.') . '/L';

        return [
            'item_id' => $discount->item_id,
            'label' => $label . ' ' . ($item->fuel_category ?? $item->name ?? 'fuel'),
        ];
    }

    /**
     * Apply an existing on-account credit (an advance, or the unapplied remainder of a
     * bigger payment - see Payment\CreateAction) to one of this buyer's invoices. No new
     * cash moves and no new journal is posted; see Payment\ApplyCreditAction.
     */
    public function applyCredit(ApplyPaymentCreditRequest $request, string $company, string $customer): RedirectResponse
    {
        $result = app(CommandBus::class)->dispatch('payment.apply_credit', [
            'customer_id' => $customer,
            'invoice_id' => $request->validated('invoice_id'),
            'amount' => $request->validated('amount'),
        ], $request->user());

        return redirect()->back()->with('success', $result['message']);
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
