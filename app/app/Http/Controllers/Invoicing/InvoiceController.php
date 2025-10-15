<?php

namespace App\Http\Controllers\Invoicing;

use App\Actions\Invoicing\InvoiceCreate;
use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\CurrencyService;
use App\Services\InvoiceService;
use App\Support\Filtering\FilterBuilder;
use App\Support\ServiceContextHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class InvoiceController extends Controller
{
    public function __construct(
        private InvoiceService $invoiceService,
        private CurrencyService $currencyService,
        private InvoiceCreate $invoiceCreate
    ) {}

    /**
     * Display a listing of invoices.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Super admin can see all invoices
        if ($user->isSuperAdmin()) {
            $query = Invoice::with(['customer', 'currency', 'items']);
        } else {
            $query = Invoice::with(['customer', 'currency', 'items'])
                ->where('company_id', $user->current_company_id);
        }

        // Apply normalized DSL filters if provided
        if ($request->filled('filters')) {
            $filters = $request->input('filters');
            if (is_string($filters)) {
                $decoded = json_decode($filters, true);
            } else {
                $decoded = is_array($filters) ? $filters : null;
            }
            if (is_array($decoded)) {
                $builder = new FilterBuilder;
                $fieldMap = [
                    'invoice_number' => 'invoice_number',
                    'status' => 'status',
                    'invoice_date' => 'invoice_date',
                    'due_date' => 'due_date',
                    'total_amount' => 'total_amount',
                    'paid_amount' => 'paid_amount',
                    'balance_due' => 'balance_due',
                    'created_at' => 'created_at',
                    'customer_name' => ['relation' => 'customer', 'column' => 'name'],
                    'currency_code' => ['relation' => 'currency', 'column' => 'code'],
                ];
                $query = $builder->apply($query, $decoded, $fieldMap);
            }
        }

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('currency_id')) {
            $query->where('currency_id', $request->currency_id);
        }

        if ($request->filled('date_from')) {
            $query->where('invoice_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('invoice_date', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        // Apply sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');

        if (in_array($sortBy, ['created_at', 'invoice_date', 'due_date', 'total_amount', 'status'])) {
            $query->orderBy($sortBy, $sortDirection);
        }

        $invoices = $query->paginate($request->input('per_page', 15))
            ->withQueryString();

        // Get available filters
        $customers = Customer::where('company_id', $request->user()->current_company_id)
            ->withTrashed() // Temporarily ignore soft deletes for this query
            ->orderBy('name')
            ->get(['customer_id as id', 'name']);

        $currencies = Currency::whereHas('companies', function ($query) use ($request) {
            $query->where('companies.id', $request->user()->current_company_id);
        })->orderBy('code')->get(['id', 'code', 'name']);

        $statusOptions = [
            ['value' => 'draft', 'label' => 'Draft'],
            ['value' => 'sent', 'label' => 'Sent'],
            ['value' => 'posted', 'label' => 'Posted'],
            ['value' => 'paid', 'label' => 'Paid'],
            ['value' => 'cancelled', 'label' => 'Cancelled'],
            ['value' => 'void', 'label' => 'Void'],
        ];

        return Inertia::render('Invoicing/Invoices/Index', [
            'invoices' => $invoices,
            'filters' => [
                'dsl' => $request->input('filters'),
                'status' => $request->input('status'),
                'customer_id' => $request->input('customer_id'),
                'currency_id' => $request->input('currency_id'),
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
                'search' => $request->input('search'),
                'sort_by' => $sortBy,
                'sort_direction' => $sortDirection,
            ],
            'customers' => $customers,
            'currencies' => $currencies,
            'statusOptions' => $statusOptions,
        ]);
    }

    /**
     * Export invoices as CSV using current filters.
     */
    public function export(Request $request)
    {
        // Reuse base query from index
        $query = Invoice::with(['customer', 'currency'])
            ->where('company_id', $request->user()->current_company_id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }
        if ($request->filled('currency_id')) {
            $query->where('currency_id', $request->currency_id);
        }
        if ($request->filled('date_from')) {
            $query->where('invoice_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('invoice_date', '<=', $request->date_to);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $rows = $query->orderBy('created_at', 'desc')->get()->map(function ($inv) {
            return [
                'Invoice #' => $inv->invoice_number,
                'Customer' => $inv->customer?->name,
                'Status' => $inv->status,
                'Invoice Date' => $inv->invoice_date,
                'Due Date' => $inv->due_date,
                'Total' => $inv->total_amount,
                'Paid' => $inv->paid_amount,
                'Balance' => $inv->balance_due,
                'Currency' => $inv->currency?->code,
            ];
        })->all();

        $filename = 'invoices-'.now()->format('Ymd-His').'.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($rows) {
            $out = fopen('php://output', 'w');
            if (isset($rows[0])) {
                fputcsv($out, array_keys($rows[0]));
            }
            foreach ($rows as $row) {
                fputcsv($out, array_values($row));
            }
            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Show the form for creating a new invoice.
     */
    public function create(Request $request)
    {
        $customers = Customer::where('company_id', $request->user()->current_company_id)
            ->orderBy('name')
            ->get(['customer_id', 'name', 'email', 'currency_id']);

        $currencies = Currency::whereHas('companies', function ($query) use ($request) {
            // Qualify column to avoid ambiguity across joined tables
            $query->where('companies.id', $request->user()->current_company_id);
        })->orderBy('code')->get(['id', 'code', 'name', 'symbol']);

        // Get the next invoice number (use model helper via a temporary instance)
        $temp = new Invoice(['company_id' => $request->user()->current_company_id]);
        $temp->setRelation('company', $request->user()->current_company); // avoid fetching
        $nextInvoiceNumber = $temp->generateInvoiceNumber();

        return Inertia::render('Invoicing/Invoices/Create', [
            'customers' => $customers,
            'currencies' => $currencies,
            'nextInvoiceNumber' => $nextInvoiceNumber,
        ]);
    }

    /**
     * Store a newly created invoice in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|uuid|exists_customer',
            'currency_id' => 'nullable|uuid|exists_currency',
            'invoice_number' => 'required|string|max:50',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'notes' => 'nullable|string|max:1000',
            'terms' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string|max:1000',
            'items.*.quantity' => 'required|numeric|min:0',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.taxes' => 'nullable|array',
            'items.*.taxes.*.name' => 'required_with:items.*.taxes|string|max:120',
            'items.*.taxes.*.rate' => 'required_with:items.*.taxes|numeric|min:0|max:100',
            'idempotency_key' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->with('error', 'Validation failed. Please check your input.')
                ->withInput();
        }

        try {
            // Use CommandBus to create invoice
            $result = $this->invoiceCreate->handle([
                'customer_id' => $request->customer_id,
                'currency_id' => $request->currency_id,
                'invoice_number' => $request->invoice_number,
                'invoice_date' => $request->invoice_date,
                'due_date' => $request->due_date,
                'notes' => $request->notes,
                'terms' => $request->terms,
                'items' => $request->items,
                'idempotency_key' => $request->idempotency_key ?? $request->header('Idempotency-Key'),
            ], $request->user());

            $invoice = Invoice::findOrFail($result['data']['id']);

            return redirect()
                ->route('invoices.show', $invoice)
                ->with('success', $result['message']);

        } catch (\Exception $e) {
            Log::error('Failed to create invoice', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
                'user_id' => $request->user()->id,
            ]);

            return back()
                ->with('error', 'Failed to create invoice. '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * Display the specified invoice.
     */
    public function show(Request $request, $id)
    {
        $this->authorize('invoices.view');

        $user = $request->user();

        // Super admin can view any invoice
        if ($user->isSuperAdmin()) {
            $invoice = Invoice::with(['customer', 'currency', 'items', 'payments', 'payments.allocations'])
                ->findOrFail($id);
        } else {
            // First find the invoice to ensure it exists, then check company access
            $invoice = Invoice::with(['customer', 'currency', 'items', 'payments', 'payments.allocations'])
                ->find($id);

            if (!$invoice || $invoice->company_id !== $user->current_company_id) {
                abort(403, 'You do not have permission to view this invoice.');
            }
        }

        return Inertia::render('Invoicing/Invoices/Show', [
            'invoice' => $invoice,
        ]);
    }

    /**
     * Show the form for editing the specified invoice.
     */
    public function edit(Request $request, $id)
    {
        $this->authorize('invoices.update');

        $user = $request->user();

        // Super admin can edit any invoice
        if ($user->isSuperAdmin()) {
            $invoice = Invoice::findOrFail($id);
        } else {
            $invoice = Invoice::where('company_id', $user->current_company_id)
                ->findOrFail($id);
        }

        if ($invoice->status !== 'draft') {
            return back()->with('error', 'Only draft invoices can be edited.');
        }

        $invoice->load(['customer', 'currency', 'items']);

        // Get customers from the invoice's company
        $customers = Customer::where('company_id', $invoice->company_id)
            ->orderBy('name')
            ->get(['customer_id', 'name', 'email', 'currency_id']);

        $currencies = Currency::whereHas('companies', function ($query) use ($request) {
            $query->where('auth.companies.id', $request->user()->current_company_id);
        })->orderBy('code')->get(['id', 'code', 'name', 'symbol']);

        return Inertia::render('Invoicing/Invoices/Edit', [
            'invoice' => $invoice,
            'customers' => $customers,
            'currencies' => $currencies,
        ]);
    }

    /**
     * Update the specified invoice in storage.
     */
    public function update(Request $request, $invoice)
    {
        $this->authorize('invoices.update');

        $user = $request->user();

        // Super admin can access any invoice
        if ($user->isSuperAdmin()) {
            $invoice = Invoice::findOrFail($invoice);
        } else {
            $invoice = Invoice::where('company_id', $user->current_company_id)
                ->findOrFail($invoice);
        }

        if ($invoice->status !== 'draft') {
            return back()->with('error', 'Only draft invoices can be updated.');
        }

        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|uuid|exists_customer',
            'currency_id' => 'nullable|uuid|exists_currency',
            'invoice_number' => 'required|string|max:50',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'notes' => 'nullable|string|max:1000',
            'terms' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string|max:1000',
            'items.*.quantity' => 'required|numeric|min:0',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.taxes' => 'nullable|array',
            'items.*.taxes.*.name' => 'required_with:items.*.taxes|string|max:120',
            'items.*.taxes.*.rate' => 'required_with:items.*.taxes|numeric|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->with('error', 'Validation failed. Please check your input.')
                ->withInput();
        }

        try {
            $customer = Customer::findOrFail($request->customer_id);
            $currency = $request->currency_id ? Currency::findOrFail($request->currency_id) : $customer->currency;

            // Get the company for service context (invoice's company for super admin, current company otherwise)
            $company = $user->isSuperAdmin() ? $invoice->company : $user->current_company;

            $updatedInvoice = $this->invoiceService->updateInvoice(
                invoice: $invoice,
                customer: $customer,
                items: $request->items,
                currency: $currency,
                invoiceDate: $request->invoice_date,
                dueDate: $request->due_date,
                notes: $request->notes,
                terms: $request->terms,
                idempotencyKey: $request->header('Idempotency-Key'),
                context: ServiceContextHelper::fromRequest($request, $company)
            );

            return redirect()
                ->route('invoices.show', $updatedInvoice)
                ->with('success', 'Invoice updated successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to update invoice', [
                'error' => $e->getMessage(),
                'invoice_id' => $invoice->getKey(),
                'user_id' => $request->user()->id,
            ]);

            return back()
                ->with('error', 'Failed to update invoice. '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * Remove the specified invoice from storage.
     */
    public function destroy(Request $request, $invoice)
    {
        $this->authorize('invoices.delete');

        $user = $request->user();

        // Super admin can access any invoice
        if ($user->isSuperAdmin()) {
            $invoice = Invoice::findOrFail($invoice);
        } else {
            $invoice = Invoice::where('company_id', $user->current_company_id)
                ->findOrFail($invoice);
        }

        if ($invoice->status !== 'draft') {
            return back()->with('error', 'Only draft invoices can be deleted.');
        }

        try {
            // Get the company for service context (invoice's company for super admin, current company otherwise)
            $company = $user->isSuperAdmin() ? $invoice->company : $user->current_company;
            $this->invoiceService->deleteInvoice($invoice, null, ServiceContextHelper::fromRequest($request, $company));

            return redirect()
                ->route('invoices.index')
                ->with('success', 'Invoice deleted successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to delete invoice', [
                'error' => $e->getMessage(),
                'invoice_id' => $invoice->getKey(),
                'user_id' => $request->user()->id,
            ]);

            return back()
                ->with('error', 'Failed to delete invoice. '.$e->getMessage());
        }
    }

    /**
     * Mark invoice as sent.
     */
    public function send(Request $request, $invoice)
    {
        $this->authorize('invoices.update');

        $user = $request->user();

        // Super admin can access any invoice
        if ($user->isSuperAdmin()) {
            $invoice = Invoice::findOrFail($invoice);
        } else {
            $invoice = Invoice::where('company_id', $user->current_company_id)
                ->findOrFail($invoice);
        }

        if ($invoice->status !== 'draft') {
            return back()->with('error', 'Only draft invoices can be marked as sent.');
        }

        try {
            // Get the company for service context (invoice's company for super admin, current company otherwise)
            $company = $user->isSuperAdmin() ? $invoice->company : $user->current_company;
            $this->invoiceService->markAsSent($invoice, ServiceContextHelper::fromRequest($request, $company));

            return back()->with('success', 'Invoice marked as sent successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to mark invoice as sent', [
                'error' => $e->getMessage(),
                'invoice_id' => $invoice->getKey(),
                'user_id' => $request->user()->id,
            ]);

            return back()->with('error', 'Failed to mark invoice as sent. '.$e->getMessage());
        }
    }

    /**
     * Post invoice to ledger.
     */
    public function post(Request $request, $invoice)
    {
        $this->authorize('invoices.update');

        $user = $request->user();

        // Super admin can access any invoice
        if ($user->isSuperAdmin()) {
            $invoice = Invoice::findOrFail($invoice);
        } else {
            $invoice = Invoice::where('company_id', $user->current_company_id)
                ->findOrFail($invoice);
        }

        if (! in_array($invoice->status, ['draft', 'sent'])) {
            return back()->with('error', 'Only draft or sent invoices can be posted.');
        }

        try {
            // Get the company for service context (invoice's company for super admin, current company otherwise)
            $company = $user->isSuperAdmin() ? $invoice->company : $user->current_company;
            $this->invoiceService->postToLedger($invoice, ServiceContextHelper::fromRequest($request, $company));

            return back()->with('success', 'Invoice posted to ledger successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to post invoice to ledger', [
                'error' => $e->getMessage(),
                'invoice_id' => $invoice->getKey(),
                'user_id' => $request->user()->id,
            ]);

            return back()->with('error', 'Failed to post invoice to ledger. '.$e->getMessage());
        }
    }

    /**
     * Cancel invoice.
     */
    public function cancel(Request $request, $invoice)
    {
        $this->authorize('invoices.update');

        $user = $request->user();

        // Super admin can access any invoice
        if ($user->isSuperAdmin()) {
            $invoice = Invoice::findOrFail($invoice);
        } else {
            $invoice = Invoice::where('company_id', $user->current_company_id)
                ->findOrFail($invoice);
        }

        if (! in_array($invoice->status, ['draft', 'sent'])) {
            return back()->with('error', 'Only draft or sent invoices can be cancelled.');
        }

        try {
            // Get the company for service context (invoice's company for super admin, current company otherwise)
            $company = $user->isSuperAdmin() ? $invoice->company : $user->current_company;

            // Pass reason; provide a default if not supplied
            $reason = trim((string) $request->input('reason', ''));
            if ($reason === '') {
                $reason = 'Cancelled by user';
            }
            $this->invoiceService->markAsCancelled($invoice, $reason, ServiceContextHelper::fromRequest($request, $company));

            return back()->with('success', 'Invoice cancelled successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to cancel invoice', [
                'error' => $e->getMessage(),
                'invoice_id' => $invoice->getKey(),
                'user_id' => $request->user()->id,
            ]);

            return back()->with('error', 'Failed to cancel invoice. '.$e->getMessage());
        }
    }

    /**
     * Update invoice status.
     */
    public function updateStatus(Request $request, $invoice)
    {
        // Check specific permission based on the status being updated
        $status = $request->input('status');

        // Map statuses to required permissions
        $statusPermissions = [
            'approved' => 'invoices.approve',
            'sent' => 'invoices.send',
            'posted' => 'invoices.post',
            'cancelled' => 'invoices.void',
            'void' => 'invoices.void',
        ];

        $permission = $statusPermissions[$status] ?? 'invoices.update';
        $this->authorize($permission);

        $user = $request->user();

        // Super admin can access any invoice
        if ($user->isSuperAdmin()) {
            $invoice = Invoice::findOrFail($invoice);
        } else {
            $invoice = Invoice::where('company_id', $user->current_company_id)
                ->findOrFail($invoice);
        }

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:draft,sent,posted,paid,cancelled,void,approved'],
            'cancellation_reason' => ['nullable', 'required_if:status,cancelled', 'string', 'min:5'],
            'void_reason' => ['nullable', 'required_if:status,void', 'string', 'min:5'],
            'reversal_reference' => ['nullable', 'required_if:status,void', 'string'],
        ]);

        // Define allowed status transitions
        $allowedTransitions = [
            'draft' => ['sent', 'cancelled', 'approved'],
            'sent' => ['draft', 'posted', 'cancelled'],
            'posted' => ['void'],
            'paid' => ['void'],
            'cancelled' => ['draft', 'sent'],
            'void' => [],
        ];

        $newStatus = $validated['status'];

        // Check if transition is allowed
        if (! in_array($newStatus, $allowedTransitions[$invoice->status] ?? [])) {
            $message = match ($invoice->status) {
                'draft' => 'Draft invoices can be marked as Sent, Cancelled, or Approved.',
                'sent' => 'Sent invoices can be marked as Draft, Posted, or Cancelled.',
                'posted' => 'Posted invoices can only be Voided.',
                'paid' => 'Paid invoices can only be Voided.',
                'cancelled' => 'Cancelled invoices can be restored to Draft or Sent.',
                'void' => 'Voided invoices cannot be changed.',
                default => 'Invalid status transition.'
            };

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => $message,
                ], 422);
            }

            return back()->with('error', $message);
        }

        try {
            $oldStatus = $invoice->status;

            // Get the company for service context (invoice's company for super admin, current company otherwise)
            $company = $user->isSuperAdmin() ? $invoice->company : $user->current_company;

            // Special handling for posting
            if ($newStatus === 'posted') {
                // Call the post service method
                $this->invoiceService->postToLedger($invoice, ServiceContextHelper::fromRequest($request, $company));
            }
            // Special handling for voiding
            elseif ($newStatus === 'void') {
                $invoice->status = 'void';
                $invoice->cancellation_reason = $validated['void_reason'] ?? 'Voided by user';
                // Handle reversal reference for posted/paid invoices
                // Create reversing journal entries for voided posted invoices
                if ($invoice->status === 'posted') {
                    try {
                        $this->journalService->createReversingEntries($invoice, ServiceContextHelper::fromRequest($request, $company));
                        
                        Log::info('Reversing journal entries created for voided invoice', [
                            'invoice_id' => $invoice->id,
                            'invoice_number' => $invoice->invoice_number,
                            'reason' => $invoice->cancellation_reason,
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Failed to create reversing journal entries', [
                            'invoice_id' => $invoice->id,
                            'error' => $e->getMessage(),
                        ]);
                        
                        // Continue with voiding but note the reversal issue
                        $invoice->reversal_status = 'failed';
                        $invoice->reversal_error = $e->getMessage();
                    }
                }
            }
            // Special handling for cancelling
            elseif ($newStatus === 'cancelled') {
                $reason = $validated['cancellation_reason'] ?? 'Cancelled by user';
                $this->invoiceService->markAsCancelled($invoice, $reason, ServiceContextHelper::fromRequest($request, $company));
            }
            // Special handling for restoring from cancelled
            elseif ($oldStatus === 'cancelled' && in_array($newStatus, ['draft', 'sent'])) {
                // Restore cancelled invoice
                $invoice->status = $newStatus;
            } else {
                $invoice->status = $newStatus;
            }

            $invoice->save();

            Log::info('Invoice status updated', [
                'invoice_id' => $invoice->getKey(),
                'user_id' => $request->user()->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Invoice status updated successfully.',
                    'invoice' => $invoice->fresh(),
                ]);
            }

            return back()->with('success', 'Invoice status updated successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to update invoice status', [
                'error' => $e->getMessage(),
                'invoice_id' => $invoice->getKey(),
                'user_id' => $request->user()->id,
                'requested_status' => $newStatus,
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Failed to update invoice status. '.$e->getMessage(),
                ], 500);
            }

            return back()->with('error', 'Failed to update invoice status. '.$e->getMessage());
        }
    }

    /**
     * Generate PDF for invoice.
     */
    public function generatePdf(Request $request, $invoice)
    {
        $this->authorize('invoices.view');

        $user = $request->user();

        // Super admin can access any invoice
        if ($user->isSuperAdmin()) {
            $invoice = Invoice::findOrFail($invoice);
        } else {
            $invoice = Invoice::where('company_id', $user->current_company_id)
                ->findOrFail($invoice);
        }

        try {
            // Get the company for service context (invoice's company for super admin, current company otherwise)
            $company = $user->isSuperAdmin() ? $invoice->company : $user->current_company;

            // Service method name is generatePDF
            $pdfPath = $this->invoiceService->generatePDF($invoice, ServiceContextHelper::fromRequest($request, $company));

            return response()->download($pdfPath, "invoice-{$invoice->invoice_number}.pdf");

        } catch (\Exception $e) {
            Log::error('Failed to generate PDF', [
                'error' => $e->getMessage(),
                'invoice_id' => $invoice->getKey(),
                'user_id' => $request->user()->id,
            ]);

            return back()->with('error', 'Failed to generate PDF. '.$e->getMessage());
        }
    }

    /**
     * Send invoice via email.
     */
    public function sendEmail(Request $request, $invoice)
    {
        $this->authorize('invoices.update');

        $user = $request->user();

        // Super admin can access any invoice
        if ($user->isSuperAdmin()) {
            $invoice = Invoice::findOrFail($invoice);
        } else {
            $invoice = Invoice::where('company_id', $user->current_company_id)
                ->findOrFail($invoice);
        }

        $validator = Validator::make($request->all(), [
            'email' => 'nullable|email',
            'subject' => 'nullable|string|max:200',
            'message' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->with('error', 'Validation failed. Please check your input.')
                ->withInput();
        }

        try {
            // Get the company for service context (invoice's company for super admin, current company otherwise)
            $company = $user->isSuperAdmin() ? $invoice->company : $user->current_company;

            $this->invoiceService->sendEmail(
                $invoice,
                $request->email,
                $request->subject,
                $request->message,
                ServiceContextHelper::fromRequest($request, $company)
            );

            return back()->with('success', 'Invoice email sent successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to send invoice email', [
                'error' => $e->getMessage(),
                'invoice_id' => $invoice->getKey(),
                'user_id' => $request->user()->id,
            ]);

            return back()->with('error', 'Failed to send invoice email. '.$e->getMessage());
        }
    }

    /**
     * Duplicate invoice.
     */
    public function duplicate(Request $request, $id)
    {
        $this->authorize('invoices.duplicate');

        $user = $request->user();

        // Super admin can access any invoice
        if ($user->isSuperAdmin()) {
            $invoice = Invoice::findOrFail($id);
        } else {
            $invoice = Invoice::where('company_id', $user->current_company_id)
                ->findOrFail($id);
        }

        try {
            $newInvoice = $this->invoiceService->duplicateInvoice($invoice, null, ServiceContextHelper::fromRequest($request));

            return redirect()
                ->route('invoices.show', $newInvoice)
                ->with('success', 'Invoice duplicated successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to duplicate invoice', [
                'error' => $e->getMessage(),
                'invoice_id' => $invoice->id,
                'user_id' => $request->user()->id,
            ]);

            return back()->with('error', 'Failed to duplicate invoice. '.$e->getMessage());
        }
    }

    /**
     * Bulk operations on invoices: send, post, cancel, delete, remind
     */
    public function bulk(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|string|in:send,post,cancel,delete,remind',
            'invoice_ids' => 'required|array|min:1',
            'invoice_ids.*' => 'uuid|exists:invoices,invoice_id',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $companyId = $request->user()->current_company_id;
        $action = $request->input('action');
        $ids = $request->input('invoice_ids', []);

        $processed = 0;
        $failed = 0;
        $errors = [];

        foreach ($ids as $invoiceId) {
            try {
                $invoice = Invoice::where('invoice_id', $invoiceId)->where('company_id', $companyId)->firstOrFail();
                $this->authorize('update', $invoice);

                switch ($action) {
                    case 'send':
                        $this->invoiceService->markAsSent($invoice, ServiceContextHelper::fromRequest($request));
                        break;
                    case 'post':
                        $this->invoiceService->markAsPosted($invoice, ServiceContextHelper::fromRequest($request));
                        break;
                    case 'cancel':
                        $reason = trim((string) $request->input('reason', ''));
                        if ($reason === '') {
                            $reason = 'Bulk cancel';
                        }
                        $this->invoiceService->markAsCancelled($invoice, $reason, ServiceContextHelper::fromRequest($request));
                        break;
                    case 'delete':
                        $this->authorize('delete', $invoice);
                        if ($invoice->status !== 'draft') {
                            throw new \RuntimeException('Only draft invoices can be deleted');
                        }
                        $this->invoiceService->deleteInvoice($invoice, null, ServiceContextHelper::fromRequest($request));
                        break;
                    case 'remind':
                        // Send a payment reminder email using default recipient
                        $subject = 'Payment Reminder for Invoice '.$invoice->invoice_number;
                        $message = 'This is a friendly reminder that your invoice is due. Please contact us if you have any questions.';
                        $this->invoiceService->sendEmail($invoice, null, $subject, $message, ServiceContextHelper::fromRequest($request));
                        break;
                }

                $processed++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = ['invoice_id' => $invoiceId, 'error' => $e->getMessage()];
                Log::warning('Invoice bulk action failed', [
                    'action' => $action,
                    'invoice_id' => $invoiceId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return back()->with('success', "Bulk {$action} completed. Processed: {$processed}, Failed: {$failed}")
            ->with('bulk_result', compact('processed', 'failed', 'errors'));
    }
}
