<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Facades\CompanyContext;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Http\Requests\StoreSaleRequest;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Customer;
use App\Services\CommandBus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
    public function create(): Response
    {
        $company = CompanyContext::getCompany();

        $depositAccounts = Account::where('company_id', $company->id)
            ->whereIn('subtype', ['bank', 'cash'])
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'subtype']);

        $revenueAccounts = Account::where('company_id', $company->id)
            ->where('type', 'revenue')
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return Inertia::render('accounting/sales/Create', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
                'ar_account_id' => $company->ar_account_id,
                'bank_account_id' => $company->bank_account_id,
            ],
            'depositAccounts' => $depositAccounts,
            'revenueAccounts' => $revenueAccounts,
        ]);
    }

    public function store(StoreSaleRequest $request): RedirectResponse
    {
        $company = CompanyContext::getCompany();
        $commandBus = app(CommandBus::class);

        $validated = $request->validated();

        $customer = $this->firstOrCreateCashSalesCustomer(
            companyId: $company->id,
            baseCurrency: $company->base_currency,
            companyArAccountId: $company->ar_account_id
        );

        $lineItems = array_map(function (array $item) {
            return [
                'description' => $item['description'],
                'quantity' => 1,
                'unit_price' => (float) $item['amount'],
                'tax_rate' => 0,
                'discount_rate' => 0,
                'income_account_id' => $item['income_account_id'] ?? null,
            ];
        }, $validated['line_items']);

        $total = array_reduce($validated['line_items'], function (float $carry, array $item) {
            return $carry + (float) ($item['amount'] ?? 0);
        }, 0.0);

        $invoiceResult = $commandBus->dispatch('invoice.create', [
            'customer' => $customer->id,
            'currency' => $company->base_currency,
            'date' => $validated['sale_date'],
            'due' => $validated['sale_date'],
            'draft' => false,
            'send_immediately' => true,
            'description' => 'Sale',
            'line_items' => $lineItems,
        ], $request->user());

        $invoiceId = $invoiceResult['data']['id'] ?? null;
        if (!$invoiceId) {
            return back()->with('success', $invoiceResult['message'] ?? 'Sale recorded');
        }

        $commandBus->dispatch('payment.create', [
            'invoice' => $invoiceId,
            'amount' => $total,
            'method' => 'cash',
            'currency' => $company->base_currency,
            'date' => $validated['sale_date'],
            'deposit_account_id' => $validated['deposit_account_id'],
            'ar_account_id' => $company->ar_account_id,
            'notes' => 'Sale payment',
        ], $request->user());

        return redirect()
            ->to("/{$company->slug}/invoices/{$invoiceId}")
            ->with('success', 'Sale recorded');
    }

    private function firstOrCreateCashSalesCustomer(string $companyId, string $baseCurrency, ?string $companyArAccountId): Customer
    {
        $existing = Customer::where('company_id', $companyId)
            ->whereRaw('LOWER(name) = ?', ['cash sales'])
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($companyId, $baseCurrency, $companyArAccountId) {
            $lastNumber = Customer::where('company_id', $companyId)
                ->whereNotNull('customer_number')
                ->lockForUpdate()
                ->orderByDesc('customer_number')
                ->value('customer_number');

            if ($lastNumber && preg_match('/(\d+)$/', $lastNumber, $matches)) {
                $sequence = ((int) $matches[1]) + 1;
            } else {
                $sequence = 1;
            }

            $customerNumber = 'CUST-' . str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);

            return Customer::create([
                'company_id' => $companyId,
                'customer_number' => $customerNumber,
                'name' => 'Cash Sales',
                'base_currency' => strtoupper($baseCurrency),
                'payment_terms' => 0,
                'ar_account_id' => $companyArAccountId,
                'is_active' => true,
            ]);
        });
    }
}

