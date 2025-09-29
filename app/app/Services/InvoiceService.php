<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceItemTax;
use App\Models\Item;
use App\Models\User;
use App\Support\ServiceContext;
use App\Traits\AuditLogging;
use Brick\Money\Money;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PDF;

class InvoiceService
{
    use AuditLogging;

    /**
     * Create a new invoice
     *
     * @param  Company  $company  The company to create the invoice for
     * @param  Customer  $customer  The customer to bill
     * @param  array  $items  Array of invoice items with description, quantity, unit_price, etc.
     * @param  Currency|null  $currency  The invoice currency (defaults to customer/company currency)
     * @param  string|null  $invoiceDate  The invoice date (defaults to current date)
     * @param  string|null  $dueDate  The due date (defaults based on customer payment terms)
     * @param  string|null  $notes  Additional notes for the invoice
     * @param  string|null  $terms  Payment terms
     * @param  ServiceContext  $context  The service context containing user and company information
     * @return Invoice The created invoice
     *
     * @throws \Throwable If the invoice creation fails
     */
    public function createInvoice(
        Company $company,
        Customer $customer,
        array $items,
        ?Currency $currency,
        ?string $invoiceDate,
        ?string $dueDate,
        ?string $notes,
        ?string $terms,
        ServiceContext $context
    ): Invoice {
        $idempotencyKey = $context->getIdempotencyKey();
        $result = DB::transaction(function () use ($company, $customer, $items, $currency, $invoiceDate, $dueDate, $notes, $terms, $idempotencyKey) {
            $currency = $currency ?? ($customer->currency ?? $company->currency);
            $currencyId = $currency?->id ?? $customer->currency_id ?? $company->currency_id;

            $invoice = new Invoice([
                'company_id' => $company->id,
                'customer_id' => $customer->getKey(),
                'currency_id' => $currencyId,
                'invoice_date' => $invoiceDate ?? now()->toDateString(),
                'due_date' => $dueDate ?? now()->addDays((int) ($customer->payment_terms ?? 0))->toDateString(),
                'status' => 'draft',
                'notes' => $notes,
                'terms' => $terms,
                'idempotency_key' => $idempotencyKey,
            ]);

            $attempts = 0;
            do {
                try {
                    $invoice->save();
                    break;
                } catch (\Illuminate\Database\QueryException $e) {
                    if ($e->getCode() === '23505' && $attempts < 5) {
                        $attempts++;
                        $invoice->resetInvoiceNumber();

                        continue;
                    }
                    throw $e;
                }
            } while ($attempts < 5);

            $this->createInvoiceItems($invoice, $items);
            $invoice->calculateTotals();
            $invoice->save();

            return $invoice->fresh(['items', 'customer', 'currency']);
        });

        $this->logAudit('invoice.create', [
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'currency_id' => $result->currency_id,
            'items_count' => count($items),
            'invoice_number' => $result->invoice_number,
        ], $context, result: ['invoice_id' => $result->getKey()]);

        return $result;
    }

    /**
     * Update an existing invoice
     *
     * @param  Invoice  $invoice  The invoice to update
     * @param  Customer|null  $customer  The customer to change to (if different)
     * @param  array|null  $items  New invoice items (if null, existing items are kept)
     * @param  string|null  $invoiceDate  New invoice date
     * @param  string|null  $dueDate  New due date
     * @param  string|null  $notes  New notes
     * @param  string|null  $terms  New terms
     * @param  ServiceContext  $context  The service context containing user and company information
     * @return Invoice The updated invoice
     *
     * @throws \InvalidArgumentException If the invoice cannot be edited
     * @throws \Throwable If the update operation fails
     */
    public function updateInvoice(
        Invoice $invoice,
        ?Customer $customer,
        ?array $items,
        ?string $invoiceDate,
        ?string $dueDate,
        ?string $notes,
        ?string $terms,
        ServiceContext $context
    ): Invoice {
        if (! $invoice->canBeEdited()) {
            throw new \InvalidArgumentException('Invoice cannot be edited in current status');
        }

        $oldData = $invoice->getAttributes();

        $result = DB::transaction(function () use ($invoice, $customer, $items, $invoiceDate, $dueDate, $notes, $terms) {
            if ($customer && $customer->id !== $invoice->customer_id) {
                $invoice->customer_id = $customer->id;
            }

            if ($invoiceDate) {
                $invoice->invoice_date = $invoiceDate;
            }

            if ($dueDate) {
                $invoice->due_date = $dueDate;
            }

            if ($notes !== null) {
                $invoice->notes = $notes;
            }

            if ($terms !== null) {
                $invoice->terms = $terms;
            }

            if ($items !== null) {
                $invoice->items()->delete();
                $this->createInvoiceItems($invoice, $items);
            }

            $invoice->calculateTotals();
            $invoice->save();

            return $invoice->fresh(['items', 'customer', 'currency']);
        });

        $this->logAudit('invoice.update', [
            'invoice_id' => $invoice->getKey(),
            'old_data' => $oldData,
            'changes' => [
                'customer_id' => $customer?->id !== $invoice->customer_id,
                'items_updated' => $items !== null,
                'dates_updated' => $invoiceDate !== null || $dueDate !== null,
            ],
        ], $context, result: ['updated_at' => $result->updated_at]);

        return $result;
    }

    /**
     * Delete an invoice
     *
     * @param  Invoice  $invoice  The invoice to delete
     * @param  string|null  $reason  The reason for deletion
     * @param  ServiceContext  $context  The service context containing user and company information
     *
     * @throws \InvalidArgumentException If the invoice cannot be deleted
     * @throws \Throwable If the delete operation fails
     */
    public function deleteInvoice(Invoice $invoice, ?string $reason, ServiceContext $context): void
    {
        if (! $invoice->canBeEdited()) {
            throw new \InvalidArgumentException('Invoice cannot be deleted in current status');
        }

        $invoiceData = $invoice->getAttributes();

        DB::transaction(function () use ($invoice) {
            $invoice->items()->delete();
            $invoice->delete();
        });

        $this->logAudit('invoice.delete', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'reason' => $reason,
        ], $context);
    }

    /**
     * Mark an invoice as sent
     *
     * @param  Invoice  $invoice  The invoice to mark as sent
     * @param  ServiceContext  $context  The service context containing user and company information
     * @return Invoice The updated invoice
     *
     * @throws \InvalidArgumentException If the invoice cannot be sent
     * @throws \Throwable If the operation fails
     */
    public function markAsSent(Invoice $invoice, ServiceContext $context): Invoice
    {
        if (! $invoice->canBeSent()) {
            throw new \InvalidArgumentException('Invoice cannot be sent');
        }

        $result = DB::transaction(function () use ($invoice) {
            $invoice->markAsSent();

            return $invoice->fresh();
        });

        $this->logAudit('invoice.sent', [
            'invoice_id' => $invoice->getKey(),
            'invoice_number' => $invoice->invoice_number,
            'customer_id' => $invoice->customer_id,
        ], $context, result: ['sent_at' => $result->sent_at]);

        return $result;
    }

    /**
     * Mark an invoice as posted to ledger
     *
     * @param  Invoice  $invoice  The invoice to post
     * @param  ServiceContext  $context  The service context containing user and company information
     * @return Invoice The updated invoice
     *
     * @throws \InvalidArgumentException If the invoice cannot be posted
     * @throws \Throwable If the operation fails
     */
    public function markAsPosted(Invoice $invoice, ServiceContext $context): Invoice
    {
        if (! $invoice->canBePosted()) {
            throw new \InvalidArgumentException('Invoice cannot be posted');
        }

        $result = DB::transaction(function () use ($invoice) {
            $invoice->markAsPosted();

            return $invoice->fresh();
        });

        $this->logAudit('invoice.posted', [
            'invoice_id' => $invoice->getKey(),
            'invoice_number' => $invoice->invoice_number,
            'customer_id' => $invoice->customer_id,
            'total_amount' => $invoice->total_amount,
        ], $context, result: ['posted_at' => $result->posted_at]);

        return $result;
    }

    /**
     * Back-compat wrapper for controllers expecting postToLedger().
     */
    public function postToLedger(Invoice $invoice): Invoice
    {
        return $this->markAsPosted($invoice);
    }

    /**
     * Cancel an invoice
     *
     * @param  Invoice  $invoice  The invoice to cancel
     * @param  string|null  $reason  The reason for cancellation
     * @param  ServiceContext  $context  The service context containing user and company information
     * @return Invoice The cancelled invoice
     *
     * @throws \InvalidArgumentException If the invoice cannot be cancelled
     * @throws \Throwable If the operation fails
     */
    public function markAsCancelled(Invoice $invoice, ?string $reason, ServiceContext $context): Invoice
    {
        if (! $invoice->canBeCancelled()) {
            throw new \InvalidArgumentException('Invoice cannot be cancelled');
        }

        $result = DB::transaction(function () use ($invoice, $reason) {
            $invoice->markAsCancelled($reason);

            return $invoice->fresh();
        });

        $this->logAudit('invoice.cancelled', [
            'invoice_id' => $invoice->getKey(),
            'invoice_number' => $invoice->invoice_number,
            'reason' => $reason,
        ], $context, result: ['cancelled_at' => $result->cancelled_at]);

        return $result;
    }

    /**
     * Generate a PDF for the invoice
     *
     * @param  Invoice  $invoice  The invoice to generate PDF for
     * @param  ServiceContext  $context  The service context containing user and company information
     * @return string The file path to the generated PDF
     *
     * @throws \Throwable If the PDF generation fails
     */
    public function generatePDF(Invoice $invoice, ServiceContext $context): string
    {
        $pdfData = [
            'invoice' => $invoice,
            'company' => $invoice->company,
            'customer' => $invoice->customer,
            'items' => $invoice->items,
            'subtotal' => $invoice->subtotal,
            'total_tax' => $invoice->tax_amount,
            'total_amount' => $invoice->total_amount,
            'balance_due' => $invoice->balance_due,
            'generated_at' => now(),
        ];

        $pdf = PDF::loadView('invoices.pdf', $pdfData);

        $filename = "invoice_{$invoice->invoice_number}_".now()->format('Y-m-d').'.pdf';
        $path = storage_path("app/public/invoices/{$filename}");

        // Ensure directory exists before saving
        File::ensureDirectoryExists(dirname($path));
        if (! File::exists(public_path('storage'))) {
            try {
                Artisan::call('storage:link');
            } catch (\Throwable $e) { /* non-fatal */
            }
        }
        $pdf->save($path);

        $this->logAudit('invoice.pdf_generated', [
            'invoice_id' => $invoice->getKey(),
            'filename' => $filename,
        ], $context);

        return $path;
    }

    /**
     * Send an invoice by email
     *
     * @param  Invoice  $invoice  The invoice to send
     * @param  string  $email  The email address to send to
     * @param  string|null  $message  Additional message to include
     * @param  ServiceContext  $context  The service context containing user and company information
     *
     * @throws \InvalidArgumentException If the invoice cannot be sent
     * @throws \Throwable If the email sending fails
     */
    public function sendInvoiceByEmail(Invoice $invoice, string $email, ?string $message, ServiceContext $context): void
    {
        if (! $invoice->canBeSent()) {
            throw new \InvalidArgumentException('Invoice cannot be sent');
        }

        $pdfPath = $this->generatePDF($invoice, $context);
        // Actual mailing integration deferred; mark as sent for now
        $this->markAsSent($invoice, $context);

        $this->logAudit('invoice.emailed', [
            'invoice_id' => $invoice->getKey(),
            'email' => $email,
            'pdf_path' => $pdfPath,
        ], $context);
    }

    /**
     * Simplified email flow used by controller; generates PDF and marks as sent.
     */
    /**
     * Simplified email flow: generates PDF and marks as sent
     *
     * @param  Invoice  $invoice  The invoice to send
     * @param  string|null  $email  The email address to send to
     * @param  string|null  $subject  The email subject
     * @param  string|null  $message  Additional message to include
     * @param  ServiceContext  $context  The service context containing user and company information
     *
     * @throws \Throwable If the operation fails
     */
    public function sendEmail(Invoice $invoice, ?string $email, ?string $subject, ?string $message, ServiceContext $context): void
    {
        $this->generatePDF($invoice, $context);
        $this->markAsSent($invoice, $context);

        $this->logAudit('invoice.email_requested', [
            'invoice_id' => $invoice->getKey(),
            'email' => $email,
            'subject' => $subject,
        ], $context);
    }

    /**
     * Duplicate an existing invoice
     *
     * @param  Invoice  $originalInvoice  The invoice to duplicate
     * @param  string|null  $newNotes  New notes for the duplicated invoice
     * @param  ServiceContext  $context  The service context containing user and company information
     * @return Invoice The duplicated invoice
     *
     * @throws \Throwable If the duplication fails
     */
    public function duplicateInvoice(Invoice $originalInvoice, ?string $newNotes, ServiceContext $context): Invoice
    {
        $result = DB::transaction(function () use ($originalInvoice, $newNotes) {
            $newInvoice = $originalInvoice->replicate([
                'invoice_id', 'invoice_number', 'status', 'created_at', 'updated_at',
                'sent_at', 'posted_at', 'cancelled_at',
            ]);

            $newInvoice->status = 'draft';
            $newInvoice->invoice_date = now()->toDateString();
            $newInvoice->due_date = now()->addDays($originalInvoice->customer->payment_terms)->toDateString();
            $newInvoice->notes = $newNotes ?? $originalInvoice->notes;
            $newInvoice->paid_amount = 0;
            $newInvoice->balance_due = $originalInvoice->total_amount;

            $newInvoice->save();

            foreach ($originalInvoice->items as $originalItem) {
                $newItem = $originalItem->replicate(['invoice_item_id', 'invoice_id', 'created_at', 'updated_at']);
                $newItem->invoice_id = $newInvoice->getKey();
                $newItem->save();

                foreach ($originalItem->taxes as $originalTax) {
                    $newTax = $originalTax->replicate(['id', 'invoice_item_id', 'created_at', 'updated_at']);
                    $newTax->invoice_item_id = $newItem->id;
                    $newTax->save();
                }
            }

            $newInvoice->calculateTotals();
            $newInvoice->save();

            return $newInvoice->fresh(['items', 'customer', 'currency']);
        });

        $this->logAudit('invoice.duplicated', [
            'original_invoice_id' => $originalInvoice->getKey(),
            'new_invoice_id' => $result->getKey(),
            'original_invoice_number' => $originalInvoice->invoice_number,
            'new_invoice_number' => $result->invoice_number,
        ], $context, result: ['new_invoice_id' => $result->getKey()]);

        return $result;
    }

    public function calculateInvoiceTotals(Invoice $invoice): array
    {
        $subtotal = Money::of(0, $invoice->currency->code);
        $totalTax = Money::of(0, $invoice->currency->code);

        foreach ($invoice->items as $item) {
            $itemSubtotal = Money::of($item->quantity * $item->unit_price, $invoice->currency->code);
            $itemTax = $item->getTotalTax();

            $subtotal = $subtotal->plus($itemSubtotal);
            $totalTax = $totalTax->plus($itemTax);
        }

        $totalAmount = $subtotal->plus($totalTax);
        $balanceDue = $totalAmount->minus(Money::of($invoice->paid_amount, $invoice->currency->code));

        return [
            'subtotal' => $subtotal->getAmount()->toFloat(),
            'total_tax' => $totalTax->getAmount()->toFloat(),
            'total_amount' => $totalAmount->getAmount()->toFloat(),
            'balance_due' => max(0, $balanceDue->getAmount()->toFloat()),
        ];
    }

    public function validateInvoiceItems(array $items): void
    {
        if (empty($items)) {
            throw new \InvalidArgumentException('Invoice must have at least one item');
        }

        foreach ($items as $index => $item) {
            if (! isset($item['description']) || empty(trim($item['description']))) {
                throw new \InvalidArgumentException("Item {$index} must have a description");
            }

            if (! isset($item['quantity']) || $item['quantity'] <= 0) {
                throw new \InvalidArgumentException("Item {$index} must have a positive quantity");
            }

            if (! isset($item['unit_price']) || $item['unit_price'] < 0) {
                throw new \InvalidArgumentException("Item {$index} must have a non-negative unit price");
            }

            if (isset($item['discount_percentage']) && ($item['discount_percentage'] < 0 || $item['discount_percentage'] > 100)) {
                throw new \InvalidArgumentException("Item {$index} discount percentage must be between 0 and 100");
            }

            if (isset($item['discount_amount']) && $item['discount_amount'] < 0) {
                throw new \InvalidArgumentException("Item {$index} discount amount must be non-negative");
            }
        }
    }

    private function createInvoiceItems(Invoice $invoice, array $items): void
    {
        $this->validateInvoiceItems($items);

        foreach ($items as $itemData) {
            $item = new InvoiceItem([
                'invoice_id' => $invoice->getKey(),
                'description' => $itemData['description'],
                'quantity' => $itemData['quantity'],
                'unit_price' => $itemData['unit_price'],
                'discount_amount' => $itemData['discount_amount'] ?? 0,
                'discount_percentage' => $itemData['discount_percentage'] ?? 0,
                'tax_inclusive' => $itemData['tax_inclusive'] ?? false,
            ]);

            if (isset($itemData['item_id'])) {
                $existingItem = Item::find($itemData['item_id']);
                if ($existingItem && $existingItem->company_id === $invoice->company_id) {
                    $item->item_id = $existingItem->id;
                    if (! isset($itemData['description']) || empty(trim($itemData['description']))) {
                        $item->description = $existingItem->name;
                    }
                }
            }

            $item->save();

            if (isset($itemData['taxes']) && is_array($itemData['taxes'])) {
                foreach ($itemData['taxes'] as $taxData) {
                    InvoiceItemTax::create([
                        'invoice_item_id' => $item->getKey(),
                        'tax_name' => $taxData['name'],
                        'rate' => $taxData['rate'],
                        'tax_amount' => 0,
                    ]);
                }
            }
        }
    }

    public function getInvoiceStatistics(Company $company, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = Invoice::where('company_id', $company->id);

        if ($startDate) {
            $query->where('invoice_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('invoice_date', '<=', $endDate);
        }

        $invoices = $query->get();

        return [
            'total_invoices' => $invoices->count(),
            'total_amount' => $invoices->sum('total_amount'),
            'total_paid' => $invoices->sum('paid_amount'),
            'total_outstanding' => $invoices->sum('balance_due'),
            'average_invoice_value' => $invoices->avg('total_amount'),
            'status_breakdown' => [
                'draft' => $invoices->where('status', 'draft')->count(),
                'sent' => $invoices->where('status', 'sent')->count(),
                'posted' => $invoices->where('status', 'posted')->count(),
                'partial' => $invoices->where('status', 'partial')->count(),
                'paid' => $invoices->where('status', 'paid')->count(),
                'cancelled' => $invoices->where('status', 'cancelled')->count(),
            ],
        ];
    }

    /**
     * Bulk update invoice statuses
     *
     * @param  array  $invoiceIds  Array of invoice IDs to update
     * @param  string  $newStatus  The new status (sent, posted, cancelled)
     * @param  ServiceContext  $context  The service context containing user and company information
     * @return array Array of results with success/failure status for each invoice
     */
    public function bulkUpdateStatus(array $invoiceIds, string $newStatus, ServiceContext $context): array
    {
        $results = [];

        foreach ($invoiceIds as $invoiceId) {
            try {
                $invoice = Invoice::findOrFail($invoiceId);

                switch ($newStatus) {
                    case 'sent':
                        $invoice = $this->markAsSent($invoice, $context);
                        break;
                    case 'posted':
                        $invoice = $this->markAsPosted($invoice, $context);
                        break;
                    case 'cancelled':
                        $invoice = $this->markAsCancelled($invoice, null, $context);
                        break;
                    default:
                        throw new \InvalidArgumentException("Unsupported status transition: {$newStatus}");
                }

                $results[] = [
                    'invoice_id' => $invoiceId,
                    'success' => true,
                    'new_status' => $invoice->status,
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'invoice_id' => $invoiceId,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }
}
