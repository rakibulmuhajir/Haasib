<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Facades\CompanyContext;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Http\Requests\StoreInvoiceRequest;
use App\Modules\Accounting\Http\Requests\UpdateInvoiceRequest;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\Customer;
use App\Models\CompanyCurrency;
use App\Services\CommandBus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $company = CompanyContext::getCompany();

        $query = Invoice::where('company_id', $company->id)
            ->with('customer:id,name')
            ->orderBy('created_at', 'desc');

        if ($request->has('search') && $request->search) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('invoice_number', 'ilike', "%{$term}%")
                    ->orWhere('reference', 'ilike', "%{$term}%")
                    ->orWhereHas('customer', function ($subQ) use ($term) {
                        $subQ->where('name', 'ilike', "%{$term}%");
                    });
            });
        }

        if ($request->has('status') && $request->status && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('customer_id') && $request->customer_id) {
            $query->where('customer_id', $request->customer_id);
        }

        $invoices = $query->paginate(25)->withQueryString();

        return Inertia::render('accounting/invoices/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'invoices' => $invoices,
            'filters' => [
                'search' => $request->search ?? '',
                'status' => $request->status ?? 'all',
                'customer_id' => $request->customer_id ?? '',
            ],
        ]);
    }

    public function create(): Response
    {
        $company = CompanyContext::getCompany();

        $customers = Customer::where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $currencies = CompanyCurrency::where('company_id', $company->id)
            ->orderByDesc('is_base')
            ->orderBy('currency_code')
            ->get(['currency_code', 'is_base']);

        return Inertia::render('accounting/invoices/Create', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'customers' => $customers,
            'currencies' => $currencies,
        ]);
    }

    public function store(StoreInvoiceRequest $request): RedirectResponse
    {
        $company = CompanyContext::getCompany();
        $commandBus = app(CommandBus::class);

        // Transform validated data to match Action expected format
        $validated = $request->validated();
        $params = [
            'customer' => $validated['customer_id'],
            'currency' => $validated['currency'] ?? $company->base_currency ?? 'USD',
            'due' => $validated['due_date'] ?? null,
            'draft' => ($validated['status'] ?? 'draft') === 'draft',
            'payment_terms' => $validated['payment_terms'] ?? null,
            'description' => $validated['description'] ?? null,
            'line_items' => $validated['line_items'],
        ];

        $result = $commandBus->dispatch('invoice.create', $params, $request->user());

        return redirect()
            ->route('invoices.show', ['company' => $company->slug, 'invoice' => $result['data']['id']])
            ->with('success', $result['message']);
    }

    public function show(Request $request): Response
    {
        $company = CompanyContext::getCompany();

        // Get the invoice ID from route parameters explicitly
        $invoiceId = $request->route('invoice');

        $invoiceRecord = Invoice::where('company_id', $company->id)
            ->with(['customer', 'lineItems'])
            ->findOrFail($invoiceId);

        return Inertia::render('accounting/invoices/Show', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'invoice' => $invoiceRecord,
        ]);
    }

    public function edit(Request $request): Response
    {
        $company = CompanyContext::getCompany();

        $invoiceId = $request->route('invoice');
        $invoiceRecord = Invoice::where('company_id', $company->id)
            ->with(['customer', 'lineItems'])
            ->findOrFail($invoiceId);

        $customers = Customer::where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $currencies = CompanyCurrency::where('company_id', $company->id)
            ->orderByDesc('is_base')
            ->orderBy('currency_code')
            ->get(['currency_code', 'is_base']);

        return Inertia::render('accounting/invoices/Edit', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'invoice' => $invoiceRecord,
            'customers' => $customers,
            'currencies' => $currencies,
        ]);
    }

    public function update(UpdateInvoiceRequest $request): RedirectResponse
    {
        $company = CompanyContext::getCompany();

        $invoiceId = $request->route('invoice');
        $invoiceRecord = Invoice::where('company_id', $company->id)
            ->findOrFail($invoiceId);

        $commandBus = app(CommandBus::class);

        // Transform validated data to match Action expected format
        $validated = $request->validated();
        $params = [
            'id' => $invoiceRecord->id,
            'customer' => $validated['customer_id'] ?? $invoiceRecord->customer_id,
            'currency' => $validated['currency'] ?? $invoiceRecord->currency ?? $company->base_currency ?? 'USD',
            'due' => $validated['due_date'] ?? null,
            'draft' => ($validated['status'] ?? $invoiceRecord->status) === 'draft',
            'payment_terms' => $validated['payment_terms'] ?? null,
            'description' => $validated['description'] ?? null,
            'line_items' => $validated['line_items'] ?? [],
        ];

        $result = $commandBus->dispatch('invoice.update', $params, $request->user());

        return redirect()
            ->route('invoices.show', ['company' => $company->slug, 'invoice' => $invoiceRecord->id])
            ->with('success', $result['message']);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $company = CompanyContext::getCompany();

        $invoiceId = $request->route('invoice');
        $invoiceRecord = Invoice::where('company_id', $company->id)
            ->findOrFail($invoiceId);

        $commandBus = app(CommandBus::class);

        $result = $commandBus->dispatch('invoice.delete', ['id' => $invoiceRecord->id], $request->user());

        return redirect()
            ->route('invoices.index', ['company' => $company->slug])
            ->with('success', $result['message']);
    }
}