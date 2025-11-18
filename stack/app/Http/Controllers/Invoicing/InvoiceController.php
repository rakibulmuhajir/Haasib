<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoices\StoreInvoiceRequest;
use App\Http\Requests\Invoices\UpdateInvoiceRequest;
use App\Http\Requests\Invoices\ViewInvoiceRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\ServiceContextHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
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
    public function index(ViewInvoiceRequest $request): Response
    {
        try {
            // Get company from request attributes (set by SetCompanyContext middleware)
            $company = $request->attributes->get('company');
            
            if (!$company) {
                throw new \Exception('Company context not found');
            }

            $invoices = Invoice::where('company_id', $company->id)
                ->with(['customer'])
                ->orderBy('created_at', 'desc')
                ->paginate(15);

            return Inertia::render('Invoicing/Invoices/Index', [
                'invoices' => $invoices,
            ]);

        } catch (\Exception $e) {
            Log::error('Invoice listing failed', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
                'company_id' => $company->id ?? null,
            ]);

            return Inertia::render('Invoicing/Invoices/Index', [
                'invoices' => collect(),
                'error' => 'Failed to load invoices: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Show the form for creating a new invoice.
     */
    public function create(ViewInvoiceRequest $request): Response
    {
        try {
            $context = ServiceContextHelper::fromRequest($request);
            $company = $context->getCompany();

            $customers = Customer::where('company_id', $company->id)
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'email']);

            return Inertia::render('Invoicing/Invoices/Create', [
                'customers' => $customers,
            ]);

        } catch (\Exception $e) {
            Log::error('Invoice creation form failed', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
            ]);

            return Inertia::render('Invoicing/Invoices/Create', [
                'customers' => collect(),
                'error' => 'Failed to load creation form',
            ]);
        }
    }

    /**
     * Store a newly created invoice.
     */
    public function store(StoreInvoiceRequest $request): RedirectResponse
    {
        try {
            $context = ServiceContextHelper::fromRequest($request);

            $invoice = Bus::dispatch('invoices.create', [
                'customer_id' => $request->validated('customer_id'),
                'invoice_number' => $request->validated('invoice_number'),
                'issue_date' => $request->validated('issue_date'),
                'due_date' => $request->validated('due_date'),
                'currency' => $request->validated('currency'),
                'notes' => $request->validated('notes'),
                'terms' => $request->validated('terms'),
                'line_items' => $request->validated('line_items'),
            ], $context);

            return redirect()->route('invoices.show', $invoice->id)
                ->with('success', 'Invoice created successfully.');

        } catch (ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();

        } catch (\Exception $e) {
            Log::error('Invoice creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()->id,
                'company_id' => $context->getCompanyId(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to create invoice. Please try again.')
                ->withInput();
        }
    }

    /**
     * Display the specified invoice.
     */
    public function show(ViewInvoiceRequest $request, Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);

        // Load relationships with error handling
        try {
            $invoice->load(['customer', 'lineItems']);
        } catch (\Exception $e) {
            Log::warning('Failed to load some invoice relationships', [
                'error' => $e->getMessage(),
                'invoice_id' => $invoice->id,
                'user_id' => $request->user()->id,
            ]);
        }

        return Inertia::render('Invoicing/Invoices/Show', [
            'invoice' => $invoice,
        ]);
    }

    /**
     * Show the form for editing the specified invoice.
     */
    public function edit(UpdateInvoiceRequest $request, Invoice $invoice): Response
    {
        try {
            if ($invoice->status !== 'draft') {
                return redirect()->route('invoices.show', $invoice->id)
                    ->with('error', 'Only draft invoices can be edited.');
            }

            $context = ServiceContextHelper::fromRequest($request);
            $company = $context->getCompany();

            $customers = Customer::where('company_id', $company->id)
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'email']);

            $invoice->load(['lineItems']);

            return Inertia::render('Invoicing/Invoices/Edit', [
                'invoice' => $invoice,
                'customers' => $customers,
            ]);

        } catch (\Exception $e) {
            Log::error('Invoice edit form failed', [
                'error' => $e->getMessage(),
                'invoice_id' => $invoice->id,
                'user_id' => $request->user()->id,
            ]);

            return redirect()->route('invoices.show', $invoice->id)
                ->with('error', 'Failed to load edit form');
        }
    }

    /**
     * Update the specified invoice.
     */
    public function update(UpdateInvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        try {
            $context = ServiceContextHelper::fromRequest($request);

            $updatedInvoice = Bus::dispatch('invoices.update', [
                'id' => $invoice->id,
                'customer_id' => $request->validated('customer_id'),
                'invoice_number' => $request->validated('invoice_number'),
                'issue_date' => $request->validated('issue_date'),
                'due_date' => $request->validated('due_date'),
                'currency' => $request->validated('currency'),
                'notes' => $request->validated('notes'),
                'terms' => $request->validated('terms'),
                'line_items' => $request->validated('line_items'),
            ], $context);

            return redirect()->route('invoices.show', $updatedInvoice->id)
                ->with('success', 'Invoice updated successfully.');

        } catch (ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();

        } catch (\Exception $e) {
            Log::error('Invoice update failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'invoice_id' => $invoice->id,
                'user_id' => $request->user()->id,
                'company_id' => $context->getCompanyId(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to update invoice. Please try again.')
                ->withInput();
        }
    }

    /**
     * Remove the specified invoice.
     */
    public function destroy(UpdateInvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        try {
            $context = ServiceContextHelper::fromRequest($request);

            Bus::dispatch('invoices.delete', [
                'id' => $invoice->id,
            ], $context);

            return redirect()->route('invoices.index')
                ->with('success', 'Invoice deleted successfully.');

        } catch (\Exception $e) {
            Log::error('Invoice deletion failed', [
                'error' => $e->getMessage(),
                'invoice_id' => $invoice->id,
                'user_id' => $request->user()->id,
                'company_id' => $context->getCompanyId(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to delete invoice. Please try again.');
        }
    }

    /**
     * Send invoice to customer.
     */
    public function send(UpdateInvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        try {
            if ($invoice->status !== 'draft') {
                return redirect()->route('invoices.show', $invoice->id)
                    ->with('error', 'Only draft invoices can be sent.');
            }

            $context = ServiceContextHelper::fromRequest($request);

            // For now, just update status - email sending will be implemented in command
            $invoice->update(['status' => 'sent']);

            Log::info('Invoice sent', [
                'invoice_id' => $invoice->id,
                'user_id' => $request->user()->id,
                'company_id' => $context->getCompanyId(),
            ]);

            return redirect()->route('invoices.show', $invoice->id)
                ->with('success', 'Invoice sent successfully.');

        } catch (\Exception $e) {
            Log::error('Invoice sending failed', [
                'error' => $e->getMessage(),
                'invoice_id' => $invoice->id,
                'user_id' => $request->user()->id,
            ]);

            return redirect()->back()
                ->with('error', 'Failed to send invoice. Please try again.');
        }
    }
}
