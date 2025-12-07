<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Http\Requests\StoreVendorRequest;
use App\Modules\Accounting\Http\Requests\UpdateVendorRequest;
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
        return Inertia::render('accounting/vendors/Create', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
        ]);
    }

    public function store(StoreVendorRequest $request): RedirectResponse
    {
        $company = app(CompanyContextService::class)->requireCompany();
        app(CommandBus::class)->dispatch('vendor.create', [
            ...$request->validated(),
            'company_id' => $company->id,
        ], $request->user());
        return back()->with('success', 'Vendor created');
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

    public function show(string $vendor): Response
    {
        $company = app(CompanyContextService::class)->requireCompany();
        $record = \App\Modules\Accounting\Models\Vendor::where('company_id', $company->id)
            ->withCount('bills')
            ->findOrFail($vendor);

        $unpaid = \App\Modules\Accounting\Models\Bill::where('company_id', $company->id)
            ->where('vendor_id', $record->id)
            ->whereNotIn('status', ['paid', 'void', 'cancelled'])
            ->sum('balance');

        $bills = \App\Modules\Accounting\Models\Bill::where('company_id', $company->id)
            ->where('vendor_id', $record->id)
            ->orderByDesc('bill_date')
            ->take(5)
            ->get(['id', 'bill_number', 'bill_date', 'due_date', 'total_amount', 'balance', 'currency', 'status']);

        return Inertia::render('accounting/vendors/Show', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'vendor' => $record,
            'stats' => [
                'bill_count' => $record->bills_count,
                'unpaid' => $unpaid,
                'amount_owed' => $unpaid,
            ],
            'recentBills' => $bills,
        ]);
    }

    public function edit(string $vendor): Response
    {
        $company = app(CompanyContextService::class)->requireCompany();
        $record = \App\Modules\Accounting\Models\Vendor::where('company_id', $company->id)->findOrFail($vendor);

        return Inertia::render('accounting/vendors/Edit', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'vendor' => $record,
        ]);
    }

    public function destroy(Request $request, string $vendor): RedirectResponse
    {
        $company = app(CompanyContextService::class)->requireCompany();
        app(CommandBus::class)->dispatch('vendor.delete', [
            'id' => $vendor,
            'company_id' => $company->id,
        ], $request->user());
        return back()->with('success', 'Vendor deleted');
    }
}
