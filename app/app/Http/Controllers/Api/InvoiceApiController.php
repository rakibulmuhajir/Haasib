<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Http\Responses\ApiResponder;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InvoiceApiController extends Controller
{
    use ApiResponder;
    public function __construct(
        private InvoiceService $invoiceService
    ) {}

/**
 * Display a listing of invoices.
 */
    public function index(Request $request): JsonResponse
    {
        $company = $request->attributes->get('company');

        $query = Invoice::where('company_id', $company->id)
            ->with(['customer', 'currency', 'items']);

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

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('date_from')) {
            $query->where('invoice_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('invoice_date', '<=', $request->date_to);
        }

        if ($request->filled('currency_id')) {
            $query->where('currency_id', $request->currency_id);
        }

        $invoices = $query->orderBy($request->sort_by ?? 'created_at', $request->sort_order ?? 'desc')
            ->paginate($request->per_page ?? 15);

        return $this->ok(
            $invoices->items(),
            null,
            [
                'current_page' => $invoices->currentPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
                'last_page' => $invoices->lastPage(),
                'filters' => [
                    'search' => $request->search,
                    'status' => $request->status,
                    'customer_id' => $request->customer_id,
                    'date_from' => $request->date_from,
                    'date_to' => $request->date_to,
                    'currency_id' => $request->currency_id,
                ],
            ]
        );
    }

    /**
     * Store a newly created invoice.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Validate input since FormRequest classes are not present
            $validated = $request->validate([
                'customer_id' => 'required|exists:customers,customer_id',
                'currency_id' => 'nullable|exists:currencies,id',
                'invoice_date' => 'nullable|date',
                'due_date' => 'nullable|date|after_or_equal:invoice_date',
                'notes' => 'nullable|string|max:1000',
                'terms' => 'nullable|string|max:2000',
                'items' => 'required|array|min:1',
                'items.*.description' => 'required|string|max:1000',
                'items.*.quantity' => 'required|numeric|min:0.0001',
                'items.*.unit_price' => 'required|numeric|min:0',
                'items.*.discount_amount' => 'nullable|numeric|min:0',
                'items.*.discount_percentage' => 'nullable|numeric|min:0|max:100',
                'items.*.taxes' => 'nullable|array',
                'items.*.taxes.*.name' => 'required_with:items.*.taxes|string|max:120',
                'items.*.taxes.*.rate' => 'required_with:items.*.taxes|numeric|min:0|max:100',
            ]);

            $company = $request->attributes->get('company');
            $customer = \App\Models\Customer::where('company_id', $company->id)
                ->where('customer_id', $validated['customer_id'])
                ->firstOrFail();
            $currency = isset($validated['currency_id']) ? \App\Models\Currency::findOrFail($validated['currency_id']) : null;

            $invoice = $this->invoiceService->createInvoice(
                company: $company,
                customer: $customer,
                items: $validated['items'],
                currency: $currency,
                invoiceDate: $validated['invoice_date'] ?? null,
                dueDate: $validated['due_date'] ?? null,
                notes: $validated['notes'] ?? null,
                terms: $validated['terms'] ?? null,
                idempotencyKey: $request->header('Idempotency-Key')
            );

            return $this->ok(
                $invoice->load(['customer', 'currency', 'items']),
                'Invoice created successfully',
                status: 201
            );

        } catch (\Exception $e) {
            Log::error('Failed to create invoice', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
                'company_id' => $request->user()->company_id,
                'request_data' => $request->all(),
            ]);

            return $this->fail('INTERNAL_ERROR', 'Failed to create invoice', 500, ['message' => $e->getMessage()]);
        }
    }

    /**
     * Display the specified invoice.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $company = $request->attributes->get('company');

        $invoice = Invoice::where('company_id', $company->id)
            ->where('invoice_id', $id)
            ->with(['customer', 'currency', 'items.taxes', 'paymentAllocations.payment'])
            ->firstOrFail();

        return $this->ok(
            $invoice,
            null,
            [
                'workflow_summary' => $invoice->getStatusWorkflowSummary(),
                'currency_summary' => $invoice->getCurrencySummary(),
                'payment_status' => $invoice->getPaymentStatusSummary(),
                'aging_info' => $invoice->getAgingInformation(),
            ]
        );
    }

    /**
     * Update the specified invoice.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            // Validate input since FormRequest classes are not present
            $validated = $request->validate([
                'customer_id' => 'nullable|exists:customers,customer_id',
                'currency_id' => 'nullable|exists:currencies,id',
                'invoice_date' => 'nullable|date',
                'due_date' => 'nullable|date|after_or_equal:invoice_date',
                'notes' => 'nullable|string|max:1000',
                'terms' => 'nullable|string|max:2000',
                'items' => 'nullable|array|min:1',
                'items.*.description' => 'required_with:items|string|max:1000',
                'items.*.quantity' => 'required_with:items|numeric|min:0.0001',
                'items.*.unit_price' => 'required_with:items|numeric|min:0',
                'items.*.discount_amount' => 'nullable|numeric|min:0',
                'items.*.discount_percentage' => 'nullable|numeric|min:0|max:100',
                'items.*.taxes' => 'nullable|array',
                'items.*.taxes.*.name' => 'required_with:items.*.taxes|string|max:120',
                'items.*.taxes.*.rate' => 'required_with:items.*.taxes|numeric|min:0|max:100',
            ]);

            $company = $request->attributes->get('company');
            $invoice = Invoice::where('company_id', $company->id)->where('invoice_id', $id)->firstOrFail();

            $customer = isset($validated['customer_id'])
                ? \App\Models\Customer::where('company_id', $company->id)
                    ->where('customer_id', $validated['customer_id'])
                    ->firstOrFail()
                : null;
            $currency = isset($validated['currency_id']) ? \App\Models\Currency::findOrFail($validated['currency_id']) : null;

            $updatedInvoice = $this->invoiceService->updateInvoice(
                invoice: $invoice,
                customer: $customer,
                items: $validated['items'] ?? null,
                invoiceDate: $validated['invoice_date'] ?? null,
                dueDate: $validated['due_date'] ?? null,
                notes: $validated['notes'] ?? null,
                terms: $validated['terms'] ?? null
            );

            return $this->ok($updatedInvoice->load(['customer', 'currency', 'items']), 'Invoice updated successfully');

        } catch (\Exception $e) {
            Log::error('Failed to update invoice', [
                'error' => $e->getMessage(),
                'invoice_id' => $id,
                'user_id' => $request->user()->id,
            ]);

            return $this->fail('INTERNAL_ERROR', 'Failed to update invoice', 500, ['message' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified invoice.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $company = $request->attributes->get('company');
            $invoice = Invoice::where('company_id', $company->id)->where('invoice_id', $id)->firstOrFail();

            $this->invoiceService->deleteInvoice($invoice, $request->reason);

            return $this->ok(null, 'Invoice deleted successfully');

        } catch (\Exception $e) {
            Log::error('Failed to delete invoice', [
                'error' => $e->getMessage(),
                'invoice_id' => $id,
                'user_id' => $request->user()->id,
            ]);

            return $this->fail('INTERNAL_ERROR', 'Failed to delete invoice', 500, ['message' => $e->getMessage()]);
        }
    }

    /**
     * Mark invoice as sent.
     */
    public function markAsSent(Request $request, string $id): JsonResponse
    {
        try {
            $company = $request->attributes->get('company');
            $invoice = Invoice::where('company_id', $company->id)->where('invoice_id', $id)->firstOrFail();

            $updatedInvoice = $this->invoiceService->markAsSent($invoice);

            return $this->ok($updatedInvoice, 'Invoice marked as sent');

        } catch (\InvalidArgumentException $e) {
            return $this->fail('VALIDATION_ERROR', $e->getMessage(), 422);
        } catch (\Exception $e) {
            \Log::error('Failed to mark invoice as sent', [
                'invoice_id' => $id,
                'error' => $e->getMessage(),
            ]);
            return $this->fail('INTERNAL_ERROR', 'Failed to mark invoice as sent', 500, ['message' => $e->getMessage()]);
        }
    }

    /**
     * Mark invoice as posted.
     */
    public function markAsPosted(Request $request, string $id): JsonResponse
    {
        try {
            $company = $request->attributes->get('company');
            $invoice = Invoice::where('company_id', $company->id)->where('invoice_id', $id)->firstOrFail();

            $updatedInvoice = $this->invoiceService->markAsPosted($invoice);

            return $this->ok($updatedInvoice, 'Invoice marked as posted');

        } catch (\InvalidArgumentException $e) {
            return $this->fail('VALIDATION_ERROR', $e->getMessage(), 422);
        } catch (\Exception $e) {
            \Log::error('Failed to mark invoice as posted', [
                'invoice_id' => $id,
                'error' => $e->getMessage(),
            ]);
            return $this->fail('INTERNAL_ERROR', 'Failed to mark invoice as posted', 500, ['message' => $e->getMessage()]);
        }
    }

    /**
     * Cancel invoice.
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'reason' => 'required|string|min:3',
            ]);

            $company = $request->attributes->get('company');
            $invoice = Invoice::where('company_id', $company->id)->where('invoice_id', $id)->firstOrFail();

            $updatedInvoice = $this->invoiceService->markAsCancelled($invoice, $request->reason);

            return $this->ok($updatedInvoice, 'Invoice cancelled successfully');

        } catch (\InvalidArgumentException $e) {
            return $this->fail('VALIDATION_ERROR', $e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->fail('INTERNAL_ERROR', 'Failed to cancel invoice', 500, ['message' => $e->getMessage()]);
        }
    }

    /**
     * Generate PDF for invoice.
     */
    public function generatePdf(Request $request, string $id): JsonResponse
    {
        try {
            $company = $request->attributes->get('company');
            $invoice = Invoice::where('company_id', $company->id)
                ->where('invoice_id', $id)
                ->firstOrFail();

            $pdfPath = $this->invoiceService->generatePDF($invoice);

            return $this->ok([
                'pdf_path' => $pdfPath,
                'pdf_url' => asset('storage/invoices/'.basename($pdfPath)),
                'generated_at' => now()->toISOString(),
            ], 'PDF generated successfully');

        } catch (\Exception $e) {
            return $this->fail('INTERNAL_ERROR', 'Failed to generate PDF', 500, ['message' => $e->getMessage()]);
        }
    }

    /**
     * Send invoice via email.
     */
    public function sendEmail(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'message' => 'nullable|string',
            ]);

            $company = $request->attributes->get('company');
            $invoice = Invoice::where('company_id', $company->id)
                ->where('invoice_id', $id)
                ->firstOrFail();

            $this->invoiceService->sendInvoiceByEmail($invoice, $request->email, $request->message);

            return $this->ok(null, 'Invoice sent successfully');

        } catch (\Exception $e) {
            return $this->fail('INTERNAL_ERROR', 'Failed to send invoice', 500, ['message' => $e->getMessage()]);
        }
    }

    /**
     * Duplicate invoice.
     */
    public function duplicate(Request $request, string $id): JsonResponse
    {
        try {
            $company = $request->attributes->get('company');
            $invoice = Invoice::where('company_id', $company->id)
                ->where('invoice_id', $id)
                ->firstOrFail();

            $duplicatedInvoice = $this->invoiceService->duplicateInvoice($invoice, $request->notes);

            return $this->ok($duplicatedInvoice->load(['customer', 'currency', 'items']), 'Invoice duplicated successfully');

        } catch (\Exception $e) {
            return $this->fail('INTERNAL_ERROR', 'Failed to duplicate invoice', 500, ['message' => $e->getMessage()]);
        }
    }

    /**
     * Check if a PDF exists for the given invoice and return its URL if found.
     */
    public function pdfExists(Request $request, string $id): JsonResponse
    {
        $company = $request->attributes->get('company');
        $invoice = Invoice::where('company_id', $company->id)
            ->where('invoice_id', $id)
            ->firstOrFail();

        $dir = storage_path('app/public/invoices');
        $pattern = sprintf('%s/invoice_%s_*.pdf', $dir, $invoice->invoice_number);
        $matches = glob($pattern) ?: [];

        $files = array_map(function ($path) {
            return [
                'filename' => basename($path),
                'path' => $path,
                'url' => asset('storage/invoices/' . basename($path)),
                'modified_at' => date('c', filemtime($path)),
            ];
        }, $matches);

        // Sort by modified time desc to get latest first
        usort($files, fn ($a, $b) => strcmp($b['modified_at'], $a['modified_at']));

        return $this->ok([
            'exists' => count($files) > 0,
            'latest' => $files[0] ?? null,
            'all' => $files,
        ]);
    }

    /**
     * Bulk operations on invoices.
     */
    public function bulk(Request $request): JsonResponse
    {
        try {
            // Validate input since FormRequest classes are not present
            $validated = $request->validate([
                'action' => 'required|string|in:delete,mark_sent,mark_posted,cancel',
                'invoice_ids' => 'required|array|min:1',
                'invoice_ids.*' => 'uuid|exists:invoices,invoice_id',
                'reason' => 'nullable|string|min:3',
            ]);

            $company = $request->attributes->get('company');
            $results = [];

            switch ($validated['action']) {
                case 'delete':
                    $invoices = Invoice::where('company_id', $company->id)
                        ->whereIn('invoice_id', $validated['invoice_ids'])
                        ->get();

                    foreach ($invoices as $invoice) {
                        try {
                            $this->invoiceService->deleteInvoice($invoice, $validated['reason'] ?? null);
                            $results[] = ['id' => $invoice->id, 'success' => true];
                        } catch (\Exception $e) {
                            $results[] = [
                                'id' => $invoice->id,
                                'success' => false,
                                'error' => $e->getMessage(),
                            ];
                        }
                    }
                    break;

                case 'mark_sent':
                    $results = $this->invoiceService->bulkUpdateStatus(
                        $validated['invoice_ids'],
                        'sent'
                    );
                    break;

                case 'mark_posted':
                    $results = $this->invoiceService->bulkUpdateStatus(
                        $validated['invoice_ids'],
                        'posted'
                    );
                    break;

                case 'cancel':
                    foreach ($validated['invoice_ids'] as $invoiceId) {
                        try {
                            $invoice = Invoice::where('company_id', $company->id)
                                ->where('invoice_id', $invoiceId)
                                ->firstOrFail();
                            $this->invoiceService->markAsCancelled($invoice, $validated['reason'] ?? null);
                            $results[] = ['id' => $invoiceId, 'success' => true];
                        } catch (\Exception $e) {
                            $results[] = [
                                'id' => $invoiceId,
                                'success' => false,
                                'error' => $e->getMessage(),
                            ];
                        }
                    }
                    break;

                default:
                    throw new \InvalidArgumentException('Invalid bulk action');
            }

            return $this->ok([
                'action' => $validated['action'],
                'results' => $results,
                'processed_count' => count($results),
                'success_count' => count(array_filter($results, fn ($r) => $r['success'])),
            ], 'Bulk operation completed');

        } catch (\Exception $e) {
            Log::error('Failed to perform bulk operation', [
                'error' => $e->getMessage(),
                'action' => $request->input('action'),
                'user_id' => $request->user()->id,
            ]);

            return $this->fail('INTERNAL_ERROR', 'Bulk operation failed', 500, ['message' => $e->getMessage()]);
        }
    }

    /**
     * Get invoice statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        $company = $this->company($request);
        $startDate = $request->date_from;
        $endDate = $request->date_to;

        $statistics = $this->invoiceService->getInvoiceStatistics($company, $startDate, $endDate);

        return $this->ok($statistics, null, [
            'filters' => [
                'date_from' => $startDate,
                'date_to' => $endDate,
            ],
        ]);
    }
}
