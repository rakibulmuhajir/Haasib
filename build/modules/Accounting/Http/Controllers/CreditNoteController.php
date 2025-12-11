<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Facades\CompanyContext;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Http\Requests\StoreCreditNoteRequest;
use App\Modules\Accounting\Http\Requests\UpdateCreditNoteRequest;
use App\Modules\Accounting\Models\CreditNote;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Invoice;
use App\Models\CompanyCurrency;
use App\Services\CommandBus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CreditNoteController extends Controller
{
    public function index(Request $request): Response
    {
        $company = CompanyContext::getCompany();

        $query = CreditNote::where('company_id', $company->id)
            ->with('customer:id,name')
            ->orderBy('created_at', 'desc');

        if ($request->has('search') && $request->search) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('credit_note_number', 'ilike', "%{$term}%")
                    ->orWhere('reason', 'ilike', "%{$term}%")
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

        $creditNotes = $query->paginate(25)->withQueryString();

        return Inertia::render('accounting/credit-notes/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'credit_notes' => $creditNotes,
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

        // Get all invoices (credit notes can apply to any invoice)
        $invoices = Invoice::where('company_id', $company->id)
            ->whereNotIn('status', ['cancelled', 'void'])
            ->orderBy('invoice_number')
            ->get(['id', 'customer_id', 'invoice_number', 'total_amount', 'currency']);

        $currencies = CompanyCurrency::where('company_id', $company->id)
            ->orderByDesc('is_base')
            ->orderBy('currency_code')
            ->get(['currency_code', 'is_base']);

        return Inertia::render('accounting/credit-notes/Create', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'customers' => $customers,
            'invoices' => $invoices,
            'currencies' => $currencies,
        ]);
    }

    public function store(StoreCreditNoteRequest $request): RedirectResponse
    {
        $company = CompanyContext::getCompany();
        $commandBus = app(CommandBus::class);

        // Transform validated data to match Action expected format
        $validated = $request->validated();

        // Create a synthetic line item from the amount (Action requires line_items)
        $params = [
            'customer' => $validated['customer_id'],
            'invoice' => $validated['invoice_id'] ?? null,
            'credit_date' => $validated['credit_date'] ?? null,
            'reason' => $validated['reason'],
            'notes' => $validated['notes'] ?? null,
            'status' => $validated['status'] ?? 'draft',
            'line_items' => [
                [
                    'description' => $validated['reason'],
                    'quantity' => 1,
                    'unit_price' => $validated['amount'],
                    'tax_rate' => 0,
                    'discount_rate' => 0,
                ],
            ],
        ];

        try {
            $result = $commandBus->dispatch('credit_note.create', $params, $request->user());

            return redirect()
                ->route('credit-notes.show', ['company' => $company->slug, 'credit_note' => $result['data']['id']])
                ->with('success', $result['message']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()
                ->back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withErrors(['error' => $e->getMessage()])
                ->withInput();
        }
    }

    public function show(Request $request): Response
    {
        $company = CompanyContext::getCompany();

        $creditNoteId = $request->route('credit_note');
        $creditNoteRecord = CreditNote::where('company_id', $company->id)
            ->with(['customer', 'invoice:id,invoice_number'])
            ->findOrFail($creditNoteId);

        return Inertia::render('accounting/credit-notes/Show', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'credit_note' => $creditNoteRecord,
        ]);
    }

    public function edit(Request $request): Response
    {
        $company = CompanyContext::getCompany();

        $creditNoteId = $request->route('credit_note');
        $creditNoteRecord = CreditNote::where('company_id', $company->id)
            ->with(['customer', 'invoice:id,invoice_number'])
            ->findOrFail($creditNoteId);

        // Get all active customers
        $customers = Customer::where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        // Get all invoices that can be applied to (excluding cancelled/void)
        $invoices = Invoice::where('company_id', $company->id)
            ->whereNotIn('status', ['cancelled', 'void'])
            ->orderBy('invoice_number')
            ->get(['id', 'customer_id', 'invoice_number', 'total_amount', 'currency']);

        $currencies = CompanyCurrency::where('company_id', $company->id)
            ->orderByDesc('is_base')
            ->orderBy('currency_code')
            ->get(['currency_code', 'is_base']);

        return Inertia::render('accounting/credit-notes/Edit', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'credit_note' => $creditNoteRecord,
            'customers' => $customers,
            'invoices' => $invoices,
            'currencies' => $currencies,
        ]);
    }

    public function update(UpdateCreditNoteRequest $request): RedirectResponse
    {
        $company = CompanyContext::getCompany();

        $creditNoteId = $request->route('credit_note');
        $creditNoteRecord = CreditNote::where('company_id', $company->id)
            ->with('items') // Load existing items
            ->findOrFail($creditNoteId);

        $commandBus = app(CommandBus::class);

        // Transform validated data to match Action expected format
        $validated = $request->validated();

        // Create a synthetic line item from the amount (Action requires line_items)
        $amount = $validated['amount'] ?? $creditNoteRecord->amount;
        $reason = $validated['reason'] ?? $creditNoteRecord->reason;

        $params = [
            'id' => $creditNoteRecord->id,
            'customer' => $validated['customer_id'] ?? $creditNoteRecord->customer_id,
            'invoice' => $validated['invoice_id'] ?? $creditNoteRecord->invoice_id,
            'credit_date' => $validated['credit_date'] ?? null,
            'reason' => $reason,
            'notes' => $validated['notes'] ?? null,
            'status' => $validated['status'] ?? $creditNoteRecord->status,
            'line_items' => [
                [
                    'description' => $reason,
                    'quantity' => 1,
                    'unit_price' => $amount,
                    'tax_rate' => 0,
                    'discount_rate' => 0,
                ],
            ],
        ];

        $result = $commandBus->dispatch('credit_note.update', $params, $request->user());

        return redirect()
            ->route('credit-notes.show', ['company' => $company->slug, 'credit_note' => $creditNoteRecord->id])
            ->with('success', $result['message']);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $company = CompanyContext::getCompany();

        $creditNoteId = $request->route('credit_note');
        $creditNoteRecord = CreditNote::where('company_id', $company->id)
            ->findOrFail($creditNoteId);

        $commandBus = app(CommandBus::class);

        $result = $commandBus->dispatch('credit_note.delete', ['id' => $creditNoteRecord->id], $request->user());

        return redirect()
            ->route('credit-notes.index', ['company' => $company->slug])
            ->with('success', $result['message']);
    }
}