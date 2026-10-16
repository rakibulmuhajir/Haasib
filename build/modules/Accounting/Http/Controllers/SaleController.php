<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Facades\CompanyContext;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Http\Requests\StoreSaleRequest;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Services\CashSaleService;
use Illuminate\Http\RedirectResponse;
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
        $validated = $request->validated();

        $result = app(CashSaleService::class)->record(
            $company,
            $request->user(),
            $validated['sale_date'],
            $validated['deposit_account_id'],
            array_map(fn (array $item) => [
                'description' => $item['description'],
                'amount' => $item['amount'],
                'income_account_id' => $item['income_account_id'] ?? null,
            ], $validated['line_items'])
        );

        $invoiceId = $result['invoice_id'];
        if (! $invoiceId) {
            return back()->with('success', $result['invoice_result']['message'] ?? 'Sale recorded');
        }

        return redirect()
            ->to("/{$company->slug}/invoices/{$invoiceId}")
            ->with('success', 'Sale recorded');
    }
}
