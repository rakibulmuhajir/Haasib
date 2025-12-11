<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CompanyCurrency;
use App\Modules\Accounting\Http\Requests\StoreVendorRequest;
use App\Modules\Accounting\Http\Requests\UpdateVendorRequest;
use App\Modules\Accounting\Models\Account;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VendorController extends Controller
{
    public function index(Request $request): Response
    {
        $company = app(CompanyContextService::class)->requireCompany();

        $query = \App\Modules\Accounting\Models\Vendor::where('company_id', $company->id)
            ->orderBy('name');

        if ($request->filled('search')) {
            $term = $request->string('search');
            $query->where(function ($q) use ($term) {
                $q->where('vendor_number', 'ilike', "%{$term}%")
                    ->orWhere('name', 'ilike', "%{$term}%")
                    ->orWhere('email', 'ilike', "%{$term}%");
            });
        }

        if (!($request->boolean('include_inactive') ?? false)) {
            $query->where('is_active', true);
        }

        $vendors = $query->paginate(25)->withQueryString();

        return Inertia::render('accounting/vendors/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'vendors' => $vendors,
            'filters' => $request->only(['search', 'include_inactive']),
        ]);
    }

    public function create(): Response
    {
        $company = app(CompanyContextService::class)->requireCompany();
        $apAccounts = Account::where('company_id', $company->id)
            ->where('subtype', 'accounts_payable')
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return Inertia::render('accounting/vendors/Create', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'apAccounts' => $apAccounts,
        ]);
    }

    public function store(StoreVendorRequest $request): RedirectResponse
    {
        $company = app(CompanyContextService::class)->requireCompany();
        $result = app(CommandBus::class)->dispatch('vendor.create', [
            ...$request->validated(),
            'company_id' => $company->id,
        ], $request->user());

        $vendorId = $result['data']['id'] ?? null;
        if ($vendorId) {
            return redirect("/{$company->slug}/vendors/{$vendorId}")->with('success', 'Vendor created');
        }

        return redirect("/{$company->slug}/vendors")->with('success', 'Vendor created');
    }

    public function update(UpdateVendorRequest $request, string $vendor): RedirectResponse
    {
        $company = app(CompanyContextService::class)->requireCompany();
        app(CommandBus::class)->dispatch('vendor.update', [
            ...$request->validated(),
            'id' => $vendor,
            'company_id' => $company->id,
        ], $request->user());
        return back()->with('success', 'Vendor updated');
    }

    public function show(Request $request): Response
    {
        $company = app(CompanyContextService::class)->requireCompany();
        $vendorId = $request->route('vendor');
        $record = \App\Modules\Accounting\Models\Vendor::where('company_id', $company->id)
            ->withCount('bills')
            ->findOrFail($vendorId);

        $unpaid = \App\Modules\Accounting\Models\Bill::where('company_id', $company->id)
            ->where('vendor_id', $record->id)
            ->whereNotIn('status', ['paid', 'void', 'cancelled'])
            ->sum('balance');

        $overdue = \App\Modules\Accounting\Models\Bill::where('company_id', $company->id)
            ->where('vendor_id', $record->id)
            ->whereNotIn('status', ['paid', 'void', 'cancelled'])
            ->where('due_date', '<', now()->toDateString())
            ->sum('balance');

        $paidYtd = \App\Modules\Accounting\Models\BillPayment::where('company_id', $company->id)
            ->where('vendor_id', $record->id)
            ->whereYear('payment_date', now()->year)
            ->sum('amount');

        $bills = \App\Modules\Accounting\Models\Bill::where('company_id', $company->id)
            ->where('vendor_id', $record->id)
            ->orderByDesc('bill_date')
            ->take(25)
            ->get(['id', 'bill_number', 'bill_date', 'due_date', 'total_amount', 'balance', 'currency', 'status']);

        $payments = \App\Modules\Accounting\Models\BillPayment::where('company_id', $company->id)
            ->where('vendor_id', $record->id)
            ->orderByDesc('payment_date')
            ->take(25)
            ->get(['id', 'payment_number', 'payment_date', 'amount', 'currency', 'payment_method', 'reference_number']);

        $currencies = CompanyCurrency::where('company_id', $company->id)
            ->orderByDesc('is_base')
            ->orderBy('currency_code')
            ->get(['currency_code', 'is_base']);

        return Inertia::render('accounting/vendors/Show', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'vendor' => $record,
            'summary' => [
                'open_balance' => $unpaid,
                'overdue_balance' => $overdue,
                'bill_count' => $record->bills_count,
                'paid_ytd' => $paidYtd,
            ],
            'bills' => $bills,
            'payments' => $payments,
            'currencies' => $currencies,
            'canEdit' => true,
        ]);
    }

    public function edit(Request $request): Response
    {
        $company = app(CompanyContextService::class)->requireCompany();
        $vendorId = $request->route('vendor');
        $record = \App\Modules\Accounting\Models\Vendor::where('company_id', $company->id)->findOrFail($vendorId);

        $apAccounts = Account::where('company_id', $company->id)
            ->where('subtype', 'accounts_payable')
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return Inertia::render('accounting/vendors/Edit', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'vendor' => $record,
            'apAccounts' => $apAccounts,
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $company = app(CompanyContextService::class)->requireCompany();
        $vendorId = $request->route('vendor');
        app(CommandBus::class)->dispatch('vendor.delete', [
            'id' => $vendorId,
            'company_id' => $company->id,
        ], $request->user());
        return back()->with('success', 'Vendor deleted');
    }
}
