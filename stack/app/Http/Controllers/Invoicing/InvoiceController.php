<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:invoices.view')->only(['index', 'show']);
        $this->middleware('permission:invoices.create')->only(['create', 'store']);
        $this->middleware('permission:invoices.update')->only(['edit', 'update']);
        $this->middleware('permission:invoices.delete')->only(['destroy']);
    }

    /**
     * Display a listing of invoices.
     */
    public function index(Request $request): Response
    {
        $company = $request->user()->currentCompany();
        
        $invoices = Invoice::where('company_id', $company->id)
            ->with(['customer'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return Inertia::render('Invoicing/Invoices/Index', [
            'invoices' => $invoices,
        ]);
    }

    /**
     * Show the form for creating a new invoice.
     */
    public function create(Request $request): Response
    {
        $company = $request->user()->currentCompany();
        
        $customers = Customer::where('company_id', $company->id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return Inertia::render('Invoicing/Invoices/Create', [
            'customers' => $customers,
        ]);
    }

    /**
     * Store a newly created invoice.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|uuid|exists:acct.customers,id',
            'invoice_number' => 'required|string|max:50',
            'issue_date' => 'required|date',
            'due_date' => 'required|date|after:issue_date',
            'currency' => 'required|string|size:3',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'line_items' => 'required|array|min:1',
            'line_items.*.description' => 'required|string',
            'line_items.*.quantity' => 'required|numeric|min:0.01',
            'line_items.*.unit_price' => 'required|numeric|min:0',
            'line_items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        $company = $request->user()->currentCompany();
        
        $invoice = Invoice::create([
            'company_id' => $company->id,
            'customer_id' => $validated['customer_id'],
            'invoice_number' => $validated['invoice_number'],
            'issue_date' => $validated['issue_date'],
            'due_date' => $validated['due_date'],
            'currency' => $validated['currency'],
            'notes' => $validated['notes'] ?? null,
            'terms' => $validated['terms'] ?? null,
            'status' => 'draft',
            'subtotal' => $this->calculateSubtotal($validated['line_items']),
            'tax_total' => $this->calculateTaxTotal($validated['line_items']),
            'total' => $this->calculateTotal($validated['line_items']),
            'created_by_user_id' => $request->user()->id,
        ]);

        // Create line items
        foreach ($validated['line_items'] as $item) {
            $invoice->lineItems()->create([
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'tax_rate' => $item['tax_rate'] ?? 0,
                'line_total' => ($item['quantity'] * $item['unit_price']) * (1 + ($item['tax_rate'] ?? 0) / 100),
            ]);
        }

        return redirect()->route('invoices.show', $invoice->id)
            ->with('success', 'Invoice created successfully.');
    }

    /**
     * Display the specified invoice.
     */
    public function show(Request $request, Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);

        $invoice->load(['customer', 'lineItems', 'payments']);

        return Inertia::render('Invoicing/Invoices/Show', [
            'invoice' => $invoice,
        ]);
    }

    /**
     * Show the form for editing the specified invoice.
     */
    public function edit(Request $request, Invoice $invoice): Response
    {
        $this->authorize('update', $invoice);

        if ($invoice->status !== 'draft') {
            abort(403, 'Only draft invoices can be edited.');
        }

        $company = $request->user()->currentCompany();
        
        $customers = Customer::where('company_id', $company->id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $invoice->load(['lineItems']);

        return Inertia::render('Invoicing/Invoices/Edit', [
            'invoice' => $invoice,
            'customers' => $customers,
        ]);
    }

    /**
     * Update the specified invoice.
     */
    public function update(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);

        if ($invoice->status !== 'draft') {
            abort(403, 'Only draft invoices can be edited.');
        }

        $validated = $request->validate([
            'customer_id' => 'required|uuid|exists:acct.customers,id',
            'invoice_number' => 'required|string|max:50',
            'issue_date' => 'required|date',
            'due_date' => 'required|date|after:issue_date',
            'currency' => 'required|string|size:3',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'line_items' => 'required|array|min:1',
            'line_items.*.description' => 'required|string',
            'line_items.*.quantity' => 'required|numeric|min:0.01',
            'line_items.*.unit_price' => 'required|numeric|min:0',
            'line_items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        $invoice->update([
            'customer_id' => $validated['customer_id'],
            'invoice_number' => $validated['invoice_number'],
            'issue_date' => $validated['issue_date'],
            'due_date' => $validated['due_date'],
            'currency' => $validated['currency'],
            'notes' => $validated['notes'] ?? null,
            'terms' => $validated['terms'] ?? null,
            'subtotal' => $this->calculateSubtotal($validated['line_items']),
            'tax_total' => $this->calculateTaxTotal($validated['line_items']),
            'total' => $this->calculateTotal($validated['line_items']),
        ]);

        // Remove existing line items and create new ones
        $invoice->lineItems()->delete();
        
        foreach ($validated['line_items'] as $item) {
            $invoice->lineItems()->create([
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'tax_rate' => $item['tax_rate'] ?? 0,
                'line_total' => ($item['quantity'] * $item['unit_price']) * (1 + ($item['tax_rate'] ?? 0) / 100),
            ]);
        }

        return redirect()->route('invoices.show', $invoice->id)
            ->with('success', 'Invoice updated successfully.');
    }

    /**
     * Remove the specified invoice.
     */
    public function destroy(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('delete', $invoice);

        if ($invoice->status !== 'draft') {
            abort(403, 'Only draft invoices can be deleted.');
        }

        $invoice->delete();

        return redirect()->route('invoices.index')
            ->with('success', 'Invoice deleted successfully.');
    }

    /**
     * Send invoice to customer.
     */
    public function send(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);

        if ($invoice->status !== 'draft') {
            abort(403, 'Only draft invoices can be sent.');
        }

        $invoice->update(['status' => 'sent']);

        // TODO: Implement email sending logic

        return redirect()->route('invoices.show', $invoice->id)
            ->with('success', 'Invoice sent successfully.');
    }

    /**
     * Calculate subtotal from line items.
     */
    protected function calculateSubtotal(array $lineItems): float
    {
        return array_reduce($lineItems, function ($total, $item) {
            return $total + ($item['quantity'] * $item['unit_price']);
        }, 0);
    }

    /**
     * Calculate tax total from line items.
     */
    protected function calculateTaxTotal(array $lineItems): float
    {
        return array_reduce($lineItems, function ($total, $item) {
            $itemTotal = $item['quantity'] * $item['unit_price'];
            $taxRate = $item['tax_rate'] ?? 0;
            return $total + ($itemTotal * ($taxRate / 100));
        }, 0);
    }

    /**
     * Calculate total from line items.
     */
    protected function calculateTotal(array $lineItems): float
    {
        return $this->calculateSubtotal($lineItems) + $this->calculateTaxTotal($lineItems);
    }
}