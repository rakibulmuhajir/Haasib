<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Invoice\BulkInvoiceRequest;
use App\Http\Requests\Api\Invoice\StoreInvoiceRequest;
use App\Http\Requests\Api\Invoice\UpdateInvoiceRequest;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InvoiceApiController extends Controller
{
    public function __construct(
        private InvoiceService $invoiceService
    ) {}

    /**
     * Display a listing of invoices.
     */
    public function index(Request $request): JsonResponse
    {
        $company = $request->user()->company;

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

        return response()->json([
            'success' => true,
            'data' => $invoices->items(),
            'meta' => [
                'current_page' => $invoices->currentPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
                'last_page' => $invoices->lastPage(),
            ],
            'filters' => [
                'search' => $request->search,
                'status' => $request->status,
                'customer_id' => $request->customer_id,
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
                'currency_id' => $request->currency_id,
            ],
        ]);
    }

    /**
     * Store a newly created invoice.
     */
    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        try {
            $company = $request->user()->company;
            $customer = $company->customers()->findOrFail($request->customer_id);
            $currency = $request->currency_id ? $company->currencies()->findOrFail($request->currency_id) : null;

            $invoice = $this->invoiceService->createInvoice(
                company: $company,
                customer: $customer,
                items: $request->items,
                currency: $currency,
                invoiceDate: $request->invoice_date,
                dueDate: $request->due_date,
                notes: $request->notes,
                terms: $request->terms,
                idempotencyKey: $request->header('Idempotency-Key')
            );

            return response()->json([
                'success' => true,
                'data' => $invoice->load(['customer', 'currency', 'items']),
                'message' => 'Invoice created successfully',
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to create invoice', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
                'company_id' => $request->user()->company_id,
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to create invoice',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified invoice.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $company = $request->user()->company;

        $invoice = Invoice::where('company_id', $company->id)
            ->with(['customer', 'currency', 'items.taxes', 'paymentAllocations.payment'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $invoice,
            'metadata' => [
                'workflow_summary' => $invoice->getStatusWorkflowSummary(),
                'currency_summary' => $invoice->getCurrencySummary(),
                'payment_status' => $invoice->getPaymentStatusSummary(),
                'aging_info' => $invoice->getAgingInformation(),
            ],
        ]);
    }

    /**
     * Update the specified invoice.
     */
    public function update(UpdateInvoiceRequest $request, string $id): JsonResponse
    {
        try {
            $company = $request->user()->company;
            $invoice = Invoice::where('company_id', $company->id)->findOrFail($id);

            $customer = $request->customer_id ? $company->customers()->findOrFail($request->customer_id) : null;
            $currency = $request->currency_id ? $company->currencies()->findOrFail($request->currency_id) : null;

            $updatedInvoice = $this->invoiceService->updateInvoice(
                invoice: $invoice,
                customer: $customer,
                items: $request->items,
                invoiceDate: $request->invoice_date,
                dueDate: $request->due_date,
                notes: $request->notes,
                terms: $request->terms
            );

            return response()->json([
                'success' => true,
                'data' => $updatedInvoice->load(['customer', 'currency', 'items']),
                'message' => 'Invoice updated successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update invoice', [
                'error' => $e->getMessage(),
                'invoice_id' => $id,
                'user_id' => $request->user()->id,
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to update invoice',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified invoice.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $company = $request->user()->company;
            $invoice = Invoice::where('company_id', $company->id)->findOrFail($id);

            $this->invoiceService->deleteInvoice($invoice, $request->reason);

            return response()->json([
                'success' => true,
                'message' => 'Invoice deleted successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete invoice', [
                'error' => $e->getMessage(),
                'invoice_id' => $id,
                'user_id' => $request->user()->id,
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to delete invoice',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mark invoice as sent.
     */
    public function markAsSent(Request $request, string $id): JsonResponse
    {
        try {
            $company = $request->user()->company;
            $invoice = Invoice::where('company_id', $company->id)->findOrFail($id);

            $updatedInvoice = $this->invoiceService->markAsSent($invoice);

            return response()->json([
                'success' => true,
                'data' => $updatedInvoice,
                'message' => 'Invoice marked as sent',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to mark invoice as sent',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mark invoice as posted.
     */
    public function markAsPosted(Request $request, string $id): JsonResponse
    {
        try {
            $company = $request->user()->company;
            $invoice = Invoice::where('company_id', $company->id)->findOrFail($id);

            $updatedInvoice = $this->invoiceService->markAsPosted($invoice);

            return response()->json([
                'success' => true,
                'data' => $updatedInvoice,
                'message' => 'Invoice marked as posted',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to mark invoice as posted',
                'message' => $e->getMessage(),
            ], 500);
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

            $company = $request->user()->company;
            $invoice = Invoice::where('company_id', $company->id)->findOrFail($id);

            $updatedInvoice = $this->invoiceService->markAsCancelled($invoice, $request->reason);

            return response()->json([
                'success' => true,
                'data' => $updatedInvoice,
                'message' => 'Invoice cancelled successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to cancel invoice',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate PDF for invoice.
     */
    public function generatePdf(Request $request, string $id): JsonResponse
    {
        try {
            $company = $request->user()->company;
            $invoice = Invoice::where('company_id', $company->id)->findOrFail($id);

            $pdfPath = $this->invoiceService->generatePDF($invoice);

            return response()->json([
                'success' => true,
                'data' => [
                    'pdf_path' => $pdfPath,
                    'pdf_url' => asset('storage/invoices/'.basename($pdfPath)),
                    'generated_at' => now()->toISOString(),
                ],
                'message' => 'PDF generated successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to generate PDF',
                'message' => $e->getMessage(),
            ], 500);
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

            $company = $request->user()->company;
            $invoice = Invoice::where('company_id', $company->id)->findOrFail($id);

            $this->invoiceService->sendInvoiceByEmail($invoice, $request->email, $request->message);

            return response()->json([
                'success' => true,
                'message' => 'Invoice sent successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to send invoice',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Duplicate invoice.
     */
    public function duplicate(Request $request, string $id): JsonResponse
    {
        try {
            $company = $request->user()->company;
            $invoice = Invoice::where('company_id', $company->id)->findOrFail($id);

            $duplicatedInvoice = $this->invoiceService->duplicateInvoice($invoice, $request->notes);

            return response()->json([
                'success' => true,
                'data' => $duplicatedInvoice->load(['customer', 'currency', 'items']),
                'message' => 'Invoice duplicated successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to duplicate invoice',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk operations on invoices.
     */
    public function bulk(BulkInvoiceRequest $request): JsonResponse
    {
        try {
            $company = $request->user()->company;
            $results = [];

            switch ($request->action) {
                case 'delete':
                    $invoices = Invoice::where('company_id', $company->id)
                        ->whereIn('id', $request->invoice_ids)
                        ->get();

                    foreach ($invoices as $invoice) {
                        try {
                            $this->invoiceService->deleteInvoice($invoice, $request->reason);
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
                        $request->invoice_ids,
                        'sent'
                    );
                    break;

                case 'mark_posted':
                    $results = $this->invoiceService->bulkUpdateStatus(
                        $request->invoice_ids,
                        'posted'
                    );
                    break;

                case 'cancel':
                    foreach ($request->invoice_ids as $id) {
                        try {
                            $invoice = Invoice::where('company_id', $company->id)->findOrFail($id);
                            $this->invoiceService->markAsCancelled($invoice, $request->reason);
                            $results[] = ['id' => $id, 'success' => true];
                        } catch (\Exception $e) {
                            $results[] = [
                                'id' => $id,
                                'success' => false,
                                'error' => $e->getMessage(),
                            ];
                        }
                    }
                    break;

                default:
                    throw new \InvalidArgumentException('Invalid bulk action');
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'action' => $request->action,
                    'results' => $results,
                    'processed_count' => count($results),
                    'success_count' => count(array_filter($results, fn ($r) => $r['success'])),
                ],
                'message' => 'Bulk operation completed',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to perform bulk operation', [
                'error' => $e->getMessage(),
                'action' => $request->action,
                'user_id' => $request->user()->id,
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Bulk operation failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get invoice statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        $startDate = $request->date_from;
        $endDate = $request->date_to;

        $statistics = $this->invoiceService->getInvoiceStatistics($company, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $statistics,
            'filters' => [
                'date_from' => $startDate,
                'date_to' => $endDate,
            ],
        ]);
    }
}
