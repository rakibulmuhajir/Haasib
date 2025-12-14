<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Http\Requests\StoreBillRequest;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Bill;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillController extends Controller
{
    public function index(Request $request): Response
    {
        $company = app(CompanyContextService::class)->requireCompany();
        $query = \App\Modules\Accounting\Models\Bill::with('vendor')
            ->where('company_id', $company->id)
            ->orderByDesc('bill_date');

        if ($request->filled('vendor_id')) {
            $query->where('vendor_id', $request->string('vendor_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('search')) {
            $term = $request->string('search');
            $query->where(function ($q) use ($term) {
                $q->where('bill_number', 'ilike', "%{$term}%")
                    ->orWhere('vendor_invoice_number', 'ilike', "%{$term}%");
            });
        }
        if ($request->filled('from_date')) {
            $query->where('bill_date', '>=', $request->string('from_date'));
        }
        if ($request->filled('to_date')) {
            $query->where('bill_date', '<=', $request->string('to_date'));
        }

        $bills = $query->paginate(25)->withQueryString();
        $vendors = \App\Modules\Accounting\Models\Vendor::where('company_id', $company->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('accounting/bills/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'bills' => $bills,
            'filters' => $request->only(['vendor_id', 'status', 'search', 'from_date', 'to_date']),
            'vendors' => $vendors,
        ]);
    }

    public function create(Request $request): Response
    {
        $company = app(CompanyContextService::class)->requireCompany();
        $vendors = \App\Modules\Accounting\Models\Vendor::where('company_id', $company->id)
            ->orderBy('name')
            ->get(['id', 'name', 'payment_terms', 'base_currency']);

        $expenseAccounts = Account::where('company_id', $company->id)
            ->whereIn('type', ['expense', 'cogs', 'asset'])
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type']);

        $apAccounts = Account::where('company_id', $company->id)
            ->where('subtype', 'accounts_payable')
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        // Owner mode → simplified quick create
        if ($this->prefersOwnerMode($request)) {
            return Inertia::render('accounting/bills/QuickCreate', [
                'company' => [
                    'id' => $company->id,
                    'name' => $company->name,
                    'slug' => $company->slug,
                    'base_currency' => $company->base_currency,
                    'default_payment_terms' => $company->default_payment_terms ?? null,
                ],
                'recentVendors' => [],
                'expenseAccounts' => $expenseAccounts,
                'defaultTaxCode' => null,
                'defaultTerms' => $company->default_payment_terms ?? null,
            ]);
        }

        return Inertia::render('accounting/bills/Create', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'vendors' => $vendors,
            'expenseAccounts' => $expenseAccounts,
            'apAccounts' => $apAccounts,
        ]);
    }

    protected function prefersOwnerMode(Request $request): bool
    {
        return $request->cookie('haasib_user_mode', 'owner') !== 'accountant';
    }

    public function store(StoreBillRequest $request): RedirectResponse
    {
        $company = app(CompanyContextService::class)->requireCompany();
        $commandBus = app(CommandBus::class);

        $payload = $request->validated();
        if ($request->boolean('pay_immediately')) {
            $payload['status'] = 'received';
        }

        $result = $commandBus->dispatch('bill.create', [
            ...$payload,
            'company_id' => $company->id,
        ], $request->user());

        if ($request->boolean('pay_immediately')) {
            $billId = $result['data']['id'] ?? null;
            if (!$billId) {
                return back()->with('success', $result['message'] ?? 'Bill created');
            }

            $bill = Bill::where('company_id', $company->id)
                ->with(['vendor'])
                ->findOrFail($billId);

            $paymentAccountId = $company->bank_account_id;
            if (!$paymentAccountId) {
                $paymentAccountId = Account::where('company_id', $company->id)
                    ->whereIn('subtype', ['bank', 'cash'])
                    ->where('is_active', true)
                    ->orderBy('code')
                    ->value('id');
            }

            if (!$paymentAccountId) {
                return back()->withErrors(['payment_account_id' => 'No bank/cash account found. Add one in onboarding or Chart of Accounts.']);
            }

            $commandBus->dispatch('bill_payment.create', [
                'vendor_id' => $bill->vendor_id,
                'payment_date' => $bill->bill_date,
                'amount' => (float) $bill->total_amount,
                'currency' => $bill->currency,
                'base_currency' => $bill->base_currency,
                'exchange_rate' => $bill->exchange_rate,
                'payment_method' => 'cash',
                'payment_account_id' => $paymentAccountId,
                'ap_account_id' => $bill->vendor?->ap_account_id,
                'allocations' => [
                    [
                        'bill_id' => $bill->id,
                        'amount_allocated' => (float) $bill->total_amount,
                    ],
                ],
            ], $request->user());

            $billId = $result['data']['id'] ?? null;
            return redirect()->route('bills.show', [$company->slug, $billId])
                ->with('success', 'Bill saved and paid');
        }

        $billId = $result['data']['id'] ?? null;
        return redirect()->route('bills.show', [$company->slug, $billId])
            ->with('success', $result['message'] ?? 'Bill created');
    }

    public function show(string $company, string $bill): Response
    {
        $companyModel = app(CompanyContextService::class)->requireCompany();
        $record = \App\Modules\Accounting\Models\Bill::with(['vendor:id,name,logo_url', 'lineItems'])
            ->where('company_id', $companyModel->id)
            ->findOrFail($bill);

        return Inertia::render('accounting/bills/Show', [
            'company' => [
                'id' => $companyModel->id,
                'name' => $companyModel->name,
                'slug' => $companyModel->slug,
                'base_currency' => $companyModel->base_currency,
                'logo_url' => $companyModel->logo_url,
            ],
            'bill' => $record,
        ]);
    }

    public function edit(string $company, string $bill): Response
    {
        $companyModel = app(CompanyContextService::class)->requireCompany();
        $record = \App\Modules\Accounting\Models\Bill::with('lineItems')
            ->where('company_id', $companyModel->id)
            ->findOrFail($bill);
        $vendors = \App\Modules\Accounting\Models\Vendor::where('company_id', $companyModel->id)
            ->orderBy('name')
            ->get(['id', 'name', 'payment_terms', 'base_currency']);

        $expenseAccounts = Account::where('company_id', $companyModel->id)
            ->whereIn('type', ['expense', 'cogs', 'asset'])
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type']);

        $apAccounts = Account::where('company_id', $companyModel->id)
            ->where('subtype', 'accounts_payable')
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return Inertia::render('accounting/bills/Edit', [
            'company' => [
                'id' => $companyModel->id,
                'name' => $companyModel->name,
                'slug' => $companyModel->slug,
                'base_currency' => $companyModel->base_currency,
            ],
            'bill' => $record,
            'vendors' => $vendors,
            'expenseAccounts' => $expenseAccounts,
            'apAccounts' => $apAccounts,
        ]);
    }

    public function destroy(Request $request, string $company, string $bill): RedirectResponse
    {
        $companyModel = app(CompanyContextService::class)->requireCompany();
        app(CommandBus::class)->dispatch('bill.delete', ['id' => $bill, 'company_id' => $companyModel->id], $request->user());

        return back()->with('success', 'Bill deleted');
    }

    public function receive(Request $request, string $company, string $bill): RedirectResponse
    {
        $companyModel = app(CompanyContextService::class)->requireCompany();
        app(CommandBus::class)->dispatch('bill.receive', ['id' => $bill], $request->user());

        return back()->with('success', 'Bill marked as received');
    }
}
