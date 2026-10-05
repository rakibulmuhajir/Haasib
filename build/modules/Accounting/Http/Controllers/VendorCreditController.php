<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Http\Requests\ApplyVendorCreditRequest;
use App\Modules\Accounting\Http\Requests\PostVendorCreditRequest;
use App\Modules\Accounting\Http\Requests\StoreVendorCreditRequest;
use App\Modules\Accounting\Http\Requests\UpdateVendorCreditRequest;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use App\Services\CompanyLetterhead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VendorCreditController extends Controller
{
    public function index(Request $request): Response
    {
        $company = app(CompanyContextService::class)->requireCompany();
        $query = \App\Modules\Accounting\Models\VendorCredit::with('vendor')
            ->where('company_id', $company->id)
            ->orderByDesc('credit_date');

        if ($request->filled('vendor_id')) {
            $query->where('vendor_id', $request->string('vendor_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $credits = $query->paginate(25)->withQueryString();
        $vendors = \App\Modules\Accounting\Models\Vendor::where('company_id', $company->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('accounting/vendor-credits/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'credits' => $credits,
            'vendors' => $vendors,
            'filters' => $request->only(['vendor_id', 'status']),
        ]);
    }

    public function create(Request $request): Response
    {
        $company = app(CompanyContextService::class)->requireCompany();
        $vendors = \App\Modules\Accounting\Models\Vendor::where('company_id', $company->id)
            ->orderBy('name')
            ->get(['id', 'name', 'base_currency']);
        $expenseAccounts = \App\Modules\Accounting\Models\Account::where('company_id', $company->id)
            ->whereIn('type', ['expense', 'cogs', 'asset', 'other_expense'])
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);
        $apAccounts = \App\Modules\Accounting\Models\Account::where('company_id', $company->id)
            ->where('subtype', 'accounts_payable')
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return Inertia::render('accounting/vendor-credits/Create', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'vendors' => $vendors,
            'expenseAccounts' => $expenseAccounts,
            'apAccounts' => $apAccounts,
            // Raised from a bill, so the vendor is already settled.
            'preselect' => [
                'vendor_id' => $request->query('vendor_id'),
            ],
        ]);
    }

    public function store(StoreVendorCreditRequest $request): RedirectResponse
    {
        $company = app(CompanyContextService::class)->requireCompany();
        app(CommandBus::class)->dispatch('vendor_credit.create', [
            ...$request->validated(),
            'company_id' => $company->id,
        ], $request->user());

        return back()->with('success', 'Vendor credit created');
    }

    public function show(Request $request): Response
    {
        $company = app(CompanyContextService::class)->requireCompany();

        // Get the vendor credit ID from route parameters explicitly
        $vendorCreditId = $request->route('vendorCredit');

        $record = \App\Modules\Accounting\Models\VendorCredit::with(['vendor', 'applications.bill'])
            ->where('company_id', $company->id)
            ->findOrFail($vendorCreditId);

        return Inertia::render('accounting/vendor-credits/Show', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
                'letterhead' => app(CompanyLetterhead::class)->forCompany($company),
            ],
            'credit' => $record,
        ]);
    }

    public function edit(Request $request): Response
    {
        $company = app(CompanyContextService::class)->requireCompany();

        // Get the vendor credit ID from route parameters explicitly
        $vendorCreditId = $request->route('vendorCredit');

        $record = \App\Modules\Accounting\Models\VendorCredit::where('company_id', $company->id)->findOrFail($vendorCreditId);

        $vendors = \App\Modules\Accounting\Models\Vendor::where('company_id', $company->id)
            ->orderBy('name')
            ->get(['id', 'name']);
        $expenseAccounts = \App\Modules\Accounting\Models\Account::where('company_id', $company->id)
            ->whereIn('type', ['expense', 'cogs', 'asset', 'other_expense'])
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);
        $apAccounts = \App\Modules\Accounting\Models\Account::where('company_id', $company->id)
            ->where('subtype', 'accounts_payable')
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return Inertia::render('accounting/vendor-credits/Edit', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'credit' => [
                ...$record->toArray(),
                'amount' => (float) $record->amount,
                'applications_count' => $record->applications()->count(),
                'line_items' => $record->items()->orderBy('line_number')->get()->map(fn ($i) => [
                    'description' => $i->description,
                    'quantity' => (float) $i->quantity,
                    'unit_price' => (float) $i->unit_price,
                    'tax_rate' => (float) $i->tax_rate,
                    'discount_rate' => (float) $i->discount_rate,
                    'expense_account_id' => $i->expense_account_id ?? '',
                ])->all(),
            ],
            'vendors' => $vendors,
            'expenseAccounts' => $expenseAccounts,
            'apAccounts' => $apAccounts,
        ]);
    }

    public function apply(Request $request): Response
    {
        $company = app(CompanyContextService::class)->requireCompany();

        // Get the vendor credit ID from route parameters explicitly
        $vendorCreditId = $request->route('vendorCredit');

        $record = \App\Modules\Accounting\Models\VendorCredit::where('company_id', $company->id)->findOrFail($vendorCreditId);
        $unpaidBills = \App\Modules\Accounting\Models\Bill::where('company_id', $company->id)
            ->where('vendor_id', $record->vendor_id)
            ->whereNotIn('status', ['paid', 'void', 'cancelled'])
            ->get(['id', 'bill_number', 'balance', 'currency']);

        return Inertia::render('accounting/vendor-credits/Apply', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'credit' => $record,
            'unpaidBills' => $unpaidBills,
        ]);
    }

    public function update(UpdateVendorCreditRequest $request): RedirectResponse
    {
        return $this->dispatchAndRedirect($request, 'vendor_credit.update', $request->validated(), 'Vendor credit updated');
    }

    public function post(PostVendorCreditRequest $request): RedirectResponse
    {
        return $this->dispatchAndRedirect($request, 'vendor_credit.post', [], 'Vendor credit posted');
    }

    public function applyStore(ApplyVendorCreditRequest $request): RedirectResponse
    {
        return $this->dispatchAndRedirect($request, 'vendor_credit.apply', $request->validated(), 'Vendor credit applied');
    }

    private function dispatchAndRedirect(Request $request, string $action, array $payload, string $fallback): RedirectResponse
    {
        $company = app(CompanyContextService::class)->requireCompany();
        $id = $request->route('vendorCredit');

        try {
            $result = app(CommandBus::class)->dispatch($action, [
                ...$payload,
                'id' => $id,
                'company_id' => $company->id,
            ], $request->user());

            return redirect()->route('vendor-credits.show', [$company->slug, $id])
                ->with('success', $result['message'] ?? $fallback);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function destroy(Request $request): RedirectResponse
    {
        $company = app(CompanyContextService::class)->requireCompany();

        // Get the vendor credit ID from route parameters explicitly
        $vendorCreditId = $request->route('vendorCredit');

        try {
            app(CommandBus::class)->dispatch('vendor_credit.void', [
                'id' => $vendorCreditId,
                'company_id' => $company->id,
                'cancellation_reason' => 'voided via controller',
            ], $request->user());

            return back()->with('success', 'Vendor credit voided');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            return back()
                ->with('error', $e->getMessage())
                ->withInput();
        }
    }
}
