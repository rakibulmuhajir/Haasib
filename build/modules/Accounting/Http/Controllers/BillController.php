<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Http\Requests\StoreBillRequest;
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

    public function create(): Response
    {
        $company = app(CompanyContextService::class)->requireCompany();
        $vendors = \App\Modules\Accounting\Models\Vendor::where('company_id', $company->id)
            ->orderBy('name')
            ->get(['id', 'name', 'payment_terms', 'base_currency']);

        return Inertia::render('accounting/bills/Create', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'vendors' => $vendors,
        ]);
    }

    public function store(StoreBillRequest $request): RedirectResponse
    {
        $company = app(CompanyContextService::class)->requireCompany();
        app(CommandBus::class)->dispatch('bill.create', [
            ...$request->validated(),
            'company_id' => $company->id,
        ], $request->user());

        return back()->with('success', 'Bill created');
    }

    public function show(string $bill): Response
    {
        $company = app(CompanyContextService::class)->requireCompany();
        $record = \App\Modules\Accounting\Models\Bill::with(['vendor', 'lineItems'])
            ->where('company_id', $company->id)
            ->findOrFail($bill);

        return Inertia::render('accounting/bills/Show', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'bill' => $record,
        ]);
    }

    public function edit(string $bill): Response
    {
        $company = app(CompanyContextService::class)->requireCompany();
        $record = \App\Modules\Accounting\Models\Bill::with('lineItems')
            ->where('company_id', $company->id)
            ->findOrFail($bill);
        $vendors = \App\Modules\Accounting\Models\Vendor::where('company_id', $company->id)
            ->orderBy('name')
            ->get(['id', 'name', 'payment_terms', 'base_currency']);

        return Inertia::render('accounting/bills/Edit', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'bill' => $record,
            'vendors' => $vendors,
        ]);
    }

    public function destroy(Request $request, string $bill): RedirectResponse
    {
        $company = app(CompanyContextService::class)->requireCompany();
        app(CommandBus::class)->dispatch('bill.delete', ['id' => $bill, 'company_id' => $company->id], $request->user());

        return back()->with('success', 'Bill deleted');
    }
}
