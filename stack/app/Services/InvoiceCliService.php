<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class InvoiceCliService
{
    /**
     * Create a new invoice using CLI service.
     */
    public function createInvoice(array $data, array $lineItems = []): Invoice
    {
        $validator = Validator::make($data, [
            'company_id' => ['required', 'uuid'],
            'customer_id' => ['required', 'uuid'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'currency' => ['required', 'string', 'max:3'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return \DB::transaction(function () use ($data, $lineItems, $validator) {
            $invoice = Invoice::create(array_merge([
                'invoice_number' => Invoice::generateInvoiceNumber($data['company_id']),
                'status' => 'draft',
                'payment_status' => 'unpaid',
                'created_by_user_id' => auth()->id(),
            ], $validator->validated()));

            foreach ($lineItems as $itemData) {
                $this->addLineItem($invoice, $itemData);
            }

            $invoice->calculateTotals();

            return $invoice->fresh(['lineItems', 'customer']);
        });
    }

    /**
     * Update an existing invoice.
     */
    public function updateInvoice(Invoice $invoice, array $data): Invoice
    {
        $validator = Validator::make($data, [
            'customer_id' => ['sometimes', 'uuid'],
            'issue_date' => ['sometimes', 'date'],
            'due_date' => ['sometimes', 'date', 'after_or_equal:issue_date'],
            'currency' => ['sometimes', 'string', 'max:3'],
            'status' => ['sometimes', 'string', 'in:draft,sent,posted,paid,cancelled'],
            'payment_status' => ['sometimes', 'string', 'in:unpaid,partial,paid,overdue'],
            'notes' => ['sometimes', 'string'],
            'terms' => ['sometimes', 'string'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $invoice->update($validator->validated());

        return $invoice->fresh();
    }

    /**
     * Add line item to invoice.
     */
    public function addLineItem(Invoice $invoice, array $itemData): InvoiceLineItem
    {
        $validator = Validator::make($itemData, [
            'description' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'tax_rate' => ['sometimes', 'numeric', 'min:0'],
            'discount_amount' => ['sometimes', 'numeric', 'min:0'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $payload = $validator->validated();
        $payload['total'] = ($payload['quantity'] * $payload['unit_price']) +
                          ($payload['quantity'] * $payload['unit_price'] * ($payload['tax_rate'] ?? 0) / 100) -
                          ($payload['discount_amount'] ?? 0);

        return $invoice->lineItems()->create($payload);
    }

    /**
     * Update line item.
     */
    public function updateLineItem(InvoiceLineItem $lineItem, array $data): InvoiceLineItem
    {
        $validator = Validator::make($data, [
            'description' => ['sometimes', 'string', 'max:255'],
            'quantity' => ['sometimes', 'numeric', 'min:0'],
            'unit_price' => ['sometimes', 'numeric', 'min:0'],
            'tax_rate' => ['sometimes', 'numeric', 'min:0'],
            'discount_amount' => ['sometimes', 'numeric', 'min:0'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $payload = $validator->validated();

        // Recalculate total if necessary
        if (isset($payload['quantity']) || isset($payload['unit_price']) ||
            isset($payload['tax_rate']) || isset($payload['discount_amount'])) {
            $quantity = $payload['quantity'] ?? $lineItem->quantity;
            $unitPrice = $payload['unit_price'] ?? $lineItem->unit_price;
            $taxRate = $payload['tax_rate'] ?? $lineItem->tax_rate;
            $discountAmount = $payload['discount_amount'] ?? $lineItem->discount_amount;

            $payload['total'] = ($quantity * $unitPrice) +
                              ($quantity * $unitPrice * $taxRate / 100) -
                              $discountAmount;
        }

        $lineItem->update($payload);

        return $lineItem->fresh();
    }

    /**
     * Remove line item from invoice.
     */
    public function removeLineItem(InvoiceLineItem $lineItem): bool
    {
        return $lineItem->delete();
    }

    /**
     * Send invoice to customer.
     */
    public function sendInvoice(Invoice $invoice, array $options = []): array
    {
        if ($invoice->status === 'cancelled') {
            throw new \Exception('Cannot send cancelled invoice');
        }

        if (! $invoice->customer->email) {
            throw new \Exception('Customer has no email address');
        }

        $result = [
            'sent' => false,
            'message' => '',
            'details' => [],
        ];

        try {
            // Update invoice status
            $invoice->markAsSent();

            // Simulate email sending
            $this->simulateEmailSending($invoice, $options);

            $result['sent'] = true;
            $result['message'] = 'Invoice sent successfully';
            $result['details'] = [
                'invoice_number' => $invoice->invoice_number,
                'recipient' => $invoice->customer->email,
                'sent_at' => $invoice->sent_at->format('Y-m-d H:i:s'),
            ];

        } catch (\Throwable $exception) {
            $result['message'] = 'Failed to send invoice: '.$exception->getMessage();
            throw $exception;
        }

        return $result;
    }

    /**
     * Post invoice to ledger.
     */
    public function postInvoice(Invoice $invoice, array $options = []): array
    {
        if ($invoice->status === 'cancelled') {
            throw new \Exception('Cannot post cancelled invoice');
        }

        if ($invoice->status === 'posted') {
            throw new \Exception('Invoice is already posted');
        }

        $result = [
            'posted' => false,
            'message' => '',
            'journal_entries' => [],
        ];

        try {
            \DB::transaction(function () use ($invoice, $options, &$result) {
                // Update invoice status
                $invoice->update([
                    'status' => 'posted',
                    'posted_at' => now(),
                    'posted_by_user_id' => auth()->id(),
                ]);

                // Generate journal entries
                $journalEntries = $this->generateJournalEntries($invoice, $options);

                // In a real implementation, this would create actual journal entry records
                foreach ($journalEntries as $entry) {
                    $this->createJournalEntry($entry);
                }

                $result['posted'] = true;
                $result['message'] = 'Invoice posted to ledger successfully';
                $result['journal_entries'] = $journalEntries;
            });

        } catch (\Throwable $exception) {
            $result['message'] = 'Failed to post invoice: '.$exception->getMessage();
            throw $exception;
        }

        return $result;
    }

    /**
     * Cancel an invoice.
     */
    public function cancelInvoice(Invoice $invoice, array $options = []): array
    {
        if ($invoice->status === 'cancelled') {
            throw new \Exception('Invoice is already cancelled');
        }

        if ($invoice->payment_status === 'paid' && ! ($options['force'] ?? false)) {
            throw new \Exception('Cannot cancel fully paid invoice. Use force option to override.');
        }

        $result = [
            'cancelled' => false,
            'message' => '',
            'refunds_processed' => [],
            'credit_note_created' => false,
        ];

        try {
            \DB::transaction(function () use ($invoice, $options, &$result) {
                // Update invoice status
                $invoice->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancelled_by_user_id' => auth()->id(),
                    'cancellation_reason' => $options['reason'] ?? 'CLI cancellation',
                ]);

                // Process refunds if needed
                if ($invoice->balance_due < $invoice->total_amount) {
                    $refunds = $this->processRefunds($invoice, $options);
                    $result['refunds_processed'] = $refunds;
                }

                // Create credit note if requested
                if ($options['create_credit_note'] ?? false) {
                    $creditNote = $this->createCreditNote($invoice, $options);
                    $result['credit_note_created'] = $creditNote;
                }

                // Reverse ledger entries if requested
                if ($options['reverse_posting'] ?? false && $invoice->status === 'posted') {
                    $this->reverseLedgerEntries($invoice);
                }

                $result['cancelled'] = true;
                $result['message'] = 'Invoice cancelled successfully';
            });

        } catch (\Throwable $exception) {
            $result['message'] = 'Failed to cancel invoice: '.$exception->getMessage();
            throw $exception;
        }

        return $result;
    }

    /**
     * Duplicate an invoice.
     */
    public function duplicateInvoice(Invoice $sourceInvoice, array $options = []): Invoice
    {
        if ($sourceInvoice->lineItems->isEmpty()) {
            throw new \Exception('Cannot duplicate invoice without line items');
        }

        $duplicateData = [
            'company_id' => $sourceInvoice->company_id,
            'customer_id' => $options['customer_id'] ?? $sourceInvoice->customer_id,
            'currency' => $options['currency'] ?? $sourceInvoice->currency,
            'notes' => ($options['notes'] ?? '').
                       "\n\nDuplicated from invoice #{$sourceInvoice->invoice_number}",
            'terms' => $options['terms'] ?? $sourceInvoice->terms,
        ];

        $lineItems = $sourceInvoice->lineItems->map(function ($item) use ($options) {
            $lineItem = [
                'description' => $item->description,
                'quantity' => ($options['reset_quantities'] ?? false) ? 0 : $item->quantity,
                'unit_price' => $item->unit_price,
                'tax_rate' => $item->tax_rate,
                'discount_amount' => $item->discount_amount,
            ];

            // Apply price adjustment if specified
            if (isset($options['price_adjustment'])) {
                $lineItem['unit_price'] *= (1 + $options['price_adjustment'] / 100);
            }

            return $lineItem;
        })->toArray();

        // Add or replace items if specified
        if (! empty($options['add_items'])) {
            $lineItems = array_merge($lineItems, $options['add_items']);
        }

        if (! empty($options['items'])) {
            $lineItems = $options['items'];
        }

        return $this->createInvoice($duplicateData, $lineItems);
    }

    /**
     * Generate PDF for invoice.
     */
    public function generatePdf(Invoice $invoice, array $options = []): array
    {
        $settings = array_merge([
            'template' => 'default',
            'format' => 'A4',
            'orientation' => 'portrait',
            'compress' => false,
            'encrypt' => false,
            'watermark' => null,
        ], $options);

        $result = [
            'generated' => false,
            'file_path' => '',
            'file_size' => 0,
            'message' => '',
        ];

        try {
            $filename = "Invoice-{$invoice->invoice_number}-".now()->format('Y-m-d-His').'.pdf';
            $outputPath = storage_path("app/invoices/pdf/{$filename}");

            // Ensure directory exists
            $outputDir = dirname($outputPath);
            if (! is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            // Simulate PDF generation
            $this->simulatePdfGeneration($invoice, $outputPath, $settings);

            $result['generated'] = true;
            $result['file_path'] = $outputPath;
            $result['file_size'] = filesize($outputPath);
            $result['message'] = 'PDF generated successfully';

        } catch (\Throwable $exception) {
            $result['message'] = 'Failed to generate PDF: '.$exception->getMessage();
            throw $exception;
        }

        return $result;
    }

    /**
     * Get invoice statistics.
     */
    public function getInvoiceStatistics(string $companyId, array $filters = []): array
    {
        $query = Invoice::where('company_id', $companyId);

        // Apply filters
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('issue_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('issue_date', '<=', $filters['date_to']);
        }

        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        $invoices = $query->get();

        return [
            'total_invoices' => $invoices->count(),
            'total_amount' => $invoices->sum('total_amount'),
            'total_balance_due' => $invoices->sum('balance_due'),
            'average_amount' => $invoices->avg('total_amount'),
            'overdue_count' => $invoices->filter(fn ($invoice) => $invoice->is_overdue)->count(),
            'overdue_amount' => $invoices->filter(fn ($invoice) => $invoice->is_overdue)->sum('balance_due'),
            'paid_count' => $invoices->where('payment_status', 'paid')->count(),
            'unpaid_count' => $invoices->where('payment_status', 'unpaid')->count(),
            'partial_count' => $invoices->where('payment_status', 'partial')->count(),
            'status_breakdown' => $invoices->groupBy('status')->map->count()->toArray(),
            'payment_status_breakdown' => $invoices->groupBy('payment_status')->map->count()->toArray(),
        ];
    }

    /**
     * Search invoices.
     */
    public function searchInvoices(string $companyId, string $searchTerm, array $options = []): Collection
    {
        $query = Invoice::where('company_id', $companyId)
            ->where(function ($q) use ($searchTerm) {
                $q->where('invoice_number', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('notes', 'LIKE', "%{$searchTerm}%")
                    ->orWhereHas('customer', function ($subQ) use ($searchTerm) {
                        $subQ->where('name', 'LIKE', "%{$searchTerm}%");
                    });
            });

        // Apply additional filters
        if (! empty($options['status'])) {
            $query->where('status', $options['status']);
        }

        if (! empty($options['sort'])) {
            $query->orderBy($options['sort'], $options['order'] ?? 'desc');
        }

        if (! empty($options['limit'])) {
            $query->limit($options['limit']);
        }

        return $query->with(['customer', 'lineItems'])->get();
    }

    /**
     * Simulate email sending.
     */
    protected function simulateEmailSending(Invoice $invoice, array $options): void
    {
        // In a real implementation, this would use Laravel's Mail facade
        usleep(500000); // Simulate 0.5 second delay
    }

    /**
     * Generate journal entries for posting.
     */
    protected function generateJournalEntries(Invoice $invoice, array $options): array
    {
        $entries = [];

        // Debit Accounts Receivable
        $entries[] = [
            'account' => 'accounts_receivable',
            'debit' => $invoice->total_amount,
            'credit' => 0,
            'description' => "Invoice #{$invoice->invoice_number} - {$invoice->customer->name}",
            'reference' => $invoice->invoice_number,
            'date' => $options['posting_date'] ?? now()->toDateString(),
        ];

        // Credit Revenue
        $revenueAmount = $invoice->subtotal - $invoice->discount_amount;
        if ($revenueAmount > 0) {
            $entries[] = [
                'account' => 'revenue',
                'debit' => 0,
                'credit' => $revenueAmount,
                'description' => "Revenue from Invoice #{$invoice->invoice_number}",
                'reference' => $invoice->invoice_number,
                'date' => $options['posting_date'] ?? now()->toDateString(),
            ];
        }

        // Credit Tax Payable
        if ($invoice->tax_amount > 0) {
            $entries[] = [
                'account' => 'tax_payable',
                'debit' => 0,
                'credit' => $invoice->tax_amount,
                'description' => "Tax from Invoice #{$invoice->invoice_number}",
                'reference' => $invoice->invoice_number,
                'date' => $options['posting_date'] ?? now()->toDateString(),
            ];
        }

        return $entries;
    }

    /**
     * Create journal entry (simulation).
     */
    protected function createJournalEntry(array $entry): void
    {
        // In a real implementation, this would create actual JournalEntry records
        // For now, we'll just log it
        \Log::info('Journal Entry Created', $entry);
    }

    /**
     * Process refunds for cancelled invoice.
     */
    protected function processRefunds(Invoice $invoice, array $options): array
    {
        $refunds = [];

        foreach ($invoice->payments as $payment) {
            $refunds[] = [
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
                'method' => $payment->payment_method,
                'status' => 'pending',
            ];
        }

        return $refunds;
    }

    /**
     * Create credit note for cancelled invoice.
     */
    protected function createCreditNote(Invoice $invoice, array $options): array
    {
        // In a real implementation, this would create an actual CreditNote record
        return [
            'credit_note_number' => 'CN-'.str_replace('INV-', '', $invoice->invoice_number),
            'amount' => $invoice->balance_due,
            'status' => 'issued',
        ];
    }

    /**
     * Reverse ledger entries.
     */
    protected function reverseLedgerEntries(Invoice $invoice): void
    {
        // In a real implementation, this would create reversing journal entries
        \Log::info("Ledger entries reversed for invoice #{$invoice->invoice_number}");
    }

    /**
     * Simulate PDF generation.
     */
    protected function simulatePdfGeneration(Invoice $invoice, string $outputPath, array $settings): void
    {
        $content = "PDF INVOICE\n";
        $content .= "================\n\n";
        $content .= "Invoice Number: {$invoice->invoice_number}\n";
        $content .= "Customer: {$invoice->customer->name}\n";
        $content .= "Total Amount: \${$invoice->total_amount}\n";
        $content .= "Template: {$settings['template']}\n";
        $content .= 'Generated: '.now()->format('Y-m-d H:i:s')."\n";

        file_put_contents($outputPath, $content);
    }
}
