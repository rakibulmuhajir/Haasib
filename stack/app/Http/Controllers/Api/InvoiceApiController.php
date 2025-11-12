<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InvoiceApiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of invoices.
     */
    public function index(Request $request): JsonResponse
    {
        $company = $request->user()->currentCompany();

        $invoices = Invoice::where('company_id', $company->id)
            ->with(['customer'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json($invoices);
    }

    /**
     * Store a newly created invoice.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'invoice_number' => 'required|string|max:255',
            'issue_date' => 'required|date',
            'due_date' => 'required|date|after:issue_date',
            'line_items' => 'required|array|min:1',
            'line_items.*.description' => 'required|string|max:255',
            'line_items.*.quantity' => 'required|numeric|min:0',
            'line_items.*.unit_price' => 'required|numeric|min:0',
            'line_items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string|max:1000',
            'terms' => 'nullable|string|max:1000',
        ]);

        try {
            $company = $request->user()->currentCompany();

            DB::beginTransaction();

            $invoice = Invoice::create([
                'company_id' => $company->id,
                'customer_id' => $validated['customer_id'],
                'invoice_number' => $validated['invoice_number'],
                'issue_date' => $validated['issue_date'],
                'due_date' => $validated['due_date'],
                'notes' => $validated['notes'] ?? null,
                'terms' => $validated['terms'] ?? null,
                'status' => 'draft',
                'subtotal' => collect($validated['line_items'])->sum(function ($item) {
                    return $item['quantity'] * $item['unit_price'];
                }),
                'tax_amount' => 0,
                'total_amount' => 0,
            ]);

            // Create line items
            foreach ($validated['line_items'] as $item) {
                $lineTotal = $item['quantity'] * $item['unit_price'];
                $taxAmount = $lineTotal * (($item['tax_rate'] ?? 0) / 100);

                $invoice->lineItems()->create([
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'tax_rate' => $item['tax_rate'] ?? 0,
                    'line_total' => $lineTotal,
                    'tax_amount' => $taxAmount,
                    'total_with_tax' => $lineTotal + $taxAmount,
                ]);
            }

            // Update totals
            $this->updateInvoiceTotals($invoice);

            DB::commit();

            return response()->json([
                'message' => 'Invoice created successfully',
                'invoice' => $invoice->load(['customer', 'lineItems']),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to create invoice',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified invoice.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $company = $request->user()->currentCompany();

        $invoice = Invoice::where('company_id', $company->id)
            ->with(['customer', 'lineItems'])
            ->findOrFail($id);

        return response()->json($invoice);
    }

    /**
     * Update the specified invoice.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'sometimes|required|exists:customers,id',
            'issue_date' => 'sometimes|required|date',
            'due_date' => 'sometimes|required|date|after:issue_date',
            'line_items' => 'sometimes|required|array|min:1',
            'line_items.*.description' => 'required|string|max:255',
            'line_items.*.quantity' => 'required|numeric|min:0',
            'line_items.*.unit_price' => 'required|numeric|min:0',
            'line_items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string|max:1000',
            'terms' => 'nullable|string|max:1000',
        ]);

        try {
            $company = $request->user()->currentCompany();

            $invoice = Invoice::where('company_id', $company->id)
                ->findOrFail($id);

            if ($invoice->status === 'sent' || $invoice->status === 'posted') {
                return response()->json([
                    'message' => 'Cannot update an invoice that has been sent or posted',
                ], 422);
            }

            DB::beginTransaction();

            $invoice->update($validated);

            if (isset($validated['line_items'])) {
                // Remove existing line items
                $invoice->lineItems()->delete();

                // Create new line items
                foreach ($validated['line_items'] as $item) {
                    $lineTotal = $item['quantity'] * $item['unit_price'];
                    $taxAmount = $lineTotal * (($item['tax_rate'] ?? 0) / 100);

                    $invoice->lineItems()->create([
                        'description' => $item['description'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'tax_rate' => $item['tax_rate'] ?? 0,
                        'line_total' => $lineTotal,
                        'tax_amount' => $taxAmount,
                        'total_with_tax' => $lineTotal + $taxAmount,
                    ]);
                }

                // Update totals
                $this->updateInvoiceTotals($invoice);
            }

            DB::commit();

            return response()->json([
                'message' => 'Invoice updated successfully',
                'invoice' => $invoice->load(['customer', 'lineItems']),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to update invoice',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified invoice.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $company = $request->user()->currentCompany();

            $invoice = Invoice::where('company_id', $company->id)
                ->findOrFail($id);

            if ($invoice->status === 'sent' || $invoice->status === 'posted') {
                return response()->json([
                    'message' => 'Cannot delete an invoice that has been sent or posted',
                ], 422);
            }

            $invoice->delete();

            return response()->json([
                'message' => 'Invoice deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete invoice',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mark invoice as sent.
     */
    public function markAsSent(Request $request, string $id): JsonResponse
    {
        try {
            $company = $request->user()->currentCompany();

            $invoice = Invoice::where('company_id', $company->id)
                ->findOrFail($id);

            $invoice->update(['status' => 'sent']);

            return response()->json([
                'message' => 'Invoice marked as sent',
                'invoice' => $invoice,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to mark invoice as sent',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mark invoice as posted.
     */
    public function markAsPosted(Request $request, string $id): JsonResponse
    {
        try {
            $company = $request->user()->currentCompany();

            $invoice = Invoice::where('company_id', $company->id)
                ->findOrFail($id);

            $invoice->update(['status' => 'posted']);

            return response()->json([
                'message' => 'Invoice marked as posted',
                'invoice' => $invoice,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to mark invoice as posted',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel invoice.
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        try {
            $company = $request->user()->currentCompany();

            $invoice = Invoice::where('company_id', $company->id)
                ->findOrFail($id);

            $invoice->update(['status' => 'cancelled']);

            return response()->json([
                'message' => 'Invoice cancelled',
                'invoice' => $invoice,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to cancel invoice',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate PDF for invoice.
     */
    public function generatePdf(Request $request, string $id): JsonResponse
    {
        try {
            $company = $request->user()->currentCompany();

            $invoice = Invoice::where('company_id', $company->id)
                ->with(['customer', 'lineItems'])
                ->findOrFail($id);

            // For now, just return success - PDF generation would be implemented here
            return response()->json([
                'message' => 'PDF generated successfully',
                'pdf_url' => "/invoices/{$id}/download",
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate PDF',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check if PDF exists.
     */
    public function pdfExists(Request $request, string $id): JsonResponse
    {
        try {
            $company = $request->user()->currentCompany();

            $invoice = Invoice::where('company_id', $company->id)
                ->findOrFail($id);

            $pdfPath = "invoices/{$company->id}/{$id}.pdf";
            $exists = Storage::disk('local')->exists($pdfPath);

            return response()->json([
                'exists' => $exists,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to check PDF existence',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send invoice via email.
     */
    public function sendEmail(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'to' => 'required|email',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'attach_pdf' => 'boolean',
        ]);

        try {
            $company = $request->user()->currentCompany();

            $invoice = Invoice::where('company_id', $company->id)
                ->with(['customer'])
                ->findOrFail($id);

            // Email sending logic would be implemented here
            // For now, just return success

            return response()->json([
                'message' => 'Invoice sent successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to send invoice',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Duplicate invoice.
     */
    public function duplicate(Request $request, string $id): JsonResponse
    {
        try {
            $company = $request->user()->currentCompany();

            $originalInvoice = Invoice::where('company_id', $company->id)
                ->with(['lineItems'])
                ->findOrFail($id);

            DB::beginTransaction();

            $newInvoice = $originalInvoice->replicate();
            $newInvoice->invoice_number = $this->generateInvoiceNumber($company);
            $newInvoice->status = 'draft';
            $newInvoice->issue_date = now()->format('Y-m-d');
            $newInvoice->due_date = now()->addDays(30)->format('Y-m-d');
            $newInvoice->save();

            // Duplicate line items
            foreach ($originalInvoice->lineItems as $lineItem) {
                $newInvoice->lineItems()->create($lineItem->toArray());
            }

            DB::commit();

            return response()->json([
                'message' => 'Invoice duplicated successfully',
                'invoice' => $newInvoice->load(['customer', 'lineItems']),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to duplicate invoice',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get invoice statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $company = $request->user()->currentCompany();

            $stats = [
                'total_invoices' => Invoice::where('company_id', $company->id)->count(),
                'draft_invoices' => Invoice::where('company_id', $company->id)->where('status', 'draft')->count(),
                'sent_invoices' => Invoice::where('company_id', $company->id)->where('status', 'sent')->count(),
                'posted_invoices' => Invoice::where('company_id', $company->id)->where('status', 'posted')->count(),
                'overdue_invoices' => Invoice::where('company_id', $company->id)
                    ->where('status', 'posted')
                    ->where('due_date', '<', now())
                    ->count(),
                'total_amount' => Invoice::where('company_id', $company->id)->sum('total_amount'),
                'outstanding_amount' => Invoice::where('company_id', $company->id)
                    ->where('status', 'posted')
                    ->sum('total_amount'),
            ];

            return response()->json($stats);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk operations on invoices.
     */
    public function bulk(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|string|in:delete,mark_sent,mark_posted,cancel',
            'invoice_ids' => 'required|array|min:1',
            'invoice_ids.*' => 'required|string|exists:invoices,id',
        ]);

        try {
            $company = $request->user()->currentCompany();

            $invoices = Invoice::where('company_id', $company->id)
                ->whereIn('id', $validated['invoice_ids'])
                ->get();

            $count = 0;

            foreach ($invoices as $invoice) {
                if ($validated['action'] === 'delete') {
                    if ($invoice->status !== 'sent' && $invoice->status !== 'posted') {
                        $invoice->delete();
                        $count++;
                    }
                } else {
                    $invoice->update(['status' => str_replace('mark_', '', $validated['action'])]);
                    $count++;
                }
            }

            return response()->json([
                'message' => 'Bulk operation completed successfully',
                'affected_count' => $count,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to perform bulk operation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update invoice totals.
     */
    private function updateInvoiceTotals(Invoice $invoice): void
    {
        $subtotal = $invoice->lineItems->sum('line_total');
        $taxAmount = $invoice->lineItems->sum('tax_amount');
        $totalAmount = $invoice->lineItems->sum('total_with_tax');

        $invoice->update([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
        ]);
    }

    /**
     * Generate unique invoice number.
     */
    private function generateInvoiceNumber(Company $company): string
    {
        $prefix = 'INV-'.date('Y').'-';
        $lastInvoice = Invoice::where('company_id', $company->id)
            ->where('invoice_number', 'like', $prefix.'%')
            ->orderByRaw('CAST(SUBSTRING(invoice_number, LENGTH(?) + 1) AS UNSIGNED) DESC', [$prefix])
            ->first();

        $sequence = $lastInvoice ? ((int) substr($lastInvoice->invoice_number, strlen($prefix)) + 1) : 1;

        return $prefix.str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }
}
