<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Modules\Accounting\Services\InvoiceService;

class InvoiceUpdate extends InvoiceBaseCommand
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'invoice:update
                           {invoice : Invoice ID, number, or UUID}
                           {--customer= : New customer ID}
                           {--issue-date= : Issue date (Y-m-d format)}
                           {--due-date= : Due date (Y-m-d format)}
                           {--currency= : Currency code}
                           {--status= : New status (draft, sent, posted, paid, cancelled)}
                           {--payment-status= : Payment status (unpaid, partial, paid, overdue)}
                           {--notes= : Invoice notes}
                           {--terms= : Payment terms}
                           {--add-items= : Add line items (JSON format)}
                           {--remove-items= : Remove line items by ID (comma-separated)}
                           {--update-items= : Update line items (JSON format)}
                           {--company= : Company ID (overrides current company)}
                           {--format=table : Output format (table, json, csv, text)}
                           {--natural= : Natural language input}
                           {--quiet : Suppress output}
                           {--force : Force update even if invoice is locked}';

    /**
     * The console command description.
     */
    protected $description = 'Update an existing invoice';

    /**
     * The invoice service instance.
     */
    protected InvoiceService $invoiceService;

    /**
     * Create a new command instance.
     */
    public function __construct(InvoiceService $invoiceService)
    {
        parent::__construct();
        $this->invoiceService = $invoiceService;
    }

    /**
     * Execute the command logic.
     */
    protected function executeCommand(): int
    {
        $input = $this->parseInput();

        // Find the invoice
        $invoice = $this->findInvoice($input);

        // Validate that invoice can be updated
        if (! $this->canUpdateInvoice($invoice, $input)) {
            return self::FAILURE;
        }

        // Prepare update data
        $updateData = $this->prepareUpdateData($input);

        if (empty($updateData) && ! $this->hasLineItemChanges($input)) {
            $this->info('No changes to update.');

            return self::SUCCESS;
        }

        // Perform the update
        $this->performUpdate($invoice, $updateData, $input);

        // Display results
        $this->displayResults($invoice, $input);

        // Log the action
        $this->logExecution('invoice_updated', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'changes' => array_keys($updateData),
        ]);

        return self::SUCCESS;
    }

    /**
     * Find invoice by ID, number, or UUID.
     */
    protected function findInvoice(array $input): Invoice
    {
        $identifier = $input['invoice'] ?? $this->argument('invoice');

        // Try by UUID
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $identifier)) {
            $invoice = Invoice::where('id', $identifier)
                ->where('company_id', $this->company->id)
                ->first();
        }

        // Try by invoice number
        if (! isset($invoice)) {
            $invoice = Invoice::where('invoice_number', $identifier)
                ->where('company_id', $this->company->id)
                ->first();
        }

        // Try numeric ID (for line items)
        if (! isset($invoice) && is_numeric($identifier)) {
            $invoice = Invoice::where('id', $identifier)
                ->where('company_id', $this->company->id)
                ->first();
        }

        if (! $invoice) {
            $this->error("Invoice '{$identifier}' not found.");
            exit(1);
        }

        return $invoice;
    }

    /**
     * Check if invoice can be updated.
     */
    protected function canUpdateInvoice(Invoice $invoice, array $input): bool
    {
        // Check if invoice is locked (paid, cancelled, or posted)
        if (in_array($invoice->status, ['paid', 'cancelled']) && ! $this->option('force')) {
            $this->error("Cannot update invoice in '{$invoice->status}' status. Use --force to override.");

            return false;
        }

        // Check if posted and trying to modify line items
        if ($invoice->status === 'posted' && $this->hasLineItemChanges($input) && ! $this->option('force')) {
            $this->error('Cannot modify line items of posted invoice. Use --force to override.');

            return false;
        }

        // Check due date changes
        if (isset($input['due_date']) && $invoice->due_date->isPast()) {
            $this->warning('Invoice is already overdue. Changing due date may not affect overdue status.');
        }

        return true;
    }

    /**
     * Check if there are line item changes.
     */
    protected function hasLineItemChanges(array $input): bool
    {
        return ! empty($input['add_items']) || ! empty($input['remove_items']) || ! empty($input['update_items']);
    }

    /**
     * Prepare update data from input.
     */
    protected function prepareUpdateData(array $input): array
    {
        $updateData = [];

        // Customer update
        if (isset($input['customer'])) {
            $updateData['customer_id'] = $this->getCustomerId($input);
        }

        // Date updates
        if (isset($input['issue_date'])) {
            $updateData['issue_date'] = $input['issue_date'];
        }

        if (isset($input['due_date'])) {
            $updateData['due_date'] = $input['due_date'];
        }

        // Currency update
        if (isset($input['currency'])) {
            $updateData['currency'] = $input['currency'];
        }

        // Status updates
        if (isset($input['status'])) {
            $updateData['status'] = $input['status'];
        }

        if (isset($input['payment_status'])) {
            $updateData['payment_status'] = $input['payment_status'];
        }

        // Notes and terms
        if (isset($input['notes'])) {
            $updateData['notes'] = $input['notes'];
        }

        if (isset($input['terms'])) {
            $updateData['terms'] = $this->buildPaymentTerms($input);
        }

        return $updateData;
    }

    /**
     * Get customer ID from input.
     */
    protected function getCustomerId(array $input): string
    {
        $customer = $input['customer'] ?? $this->option('customer');

        if (! $customer) {
            return null; // No customer change
        }

        // Try to find by UUID first
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $customer)) {
            $customerModel = \App\Models\Customer::where('id', $customer)
                ->where('company_id', $this->company->id)
                ->first();

            if ($customerModel) {
                return $customerModel->id;
            }
        }

        // Try to find by name
        $customerModel = \App\Models\Customer::where('name', 'LIKE', "%{$customer}%")
            ->where('company_id', $this->company->id)
            ->first();

        if (! $customerModel) {
            $this->error("Customer '{$customer}' not found.");
            exit(1);
        }

        return $customerModel->id;
    }

    /**
     * Build payment terms from input.
     */
    protected function buildPaymentTerms(array $input): ?string
    {
        $terms = [];

        if (isset($input['terms']['payment_terms'])) {
            $terms[] = "Payment due in {$input['terms']['payment_terms']} days";
        }

        if (isset($input['terms']['late_fee_percent'])) {
            $terms[] = "Late fee: {$input['terms']['late_fee_percent']}%";
        }

        if (isset($input['terms']['interest_rate'])) {
            $terms[] = "Interest rate: {$input['terms']['interest_rate']}% per annum";
        }

        return empty($terms) ? null : implode('. ', $terms);
    }

    /**
     * Perform the actual update.
     */
    protected function performUpdate(Invoice $invoice, array $updateData, array $input): void
    {
        \DB::transaction(function () use ($invoice, $updateData, $input) {
            // Update invoice fields
            if (! empty($updateData)) {
                $invoice->update($updateData);
            }

            // Handle line item changes
            $this->handleLineItemChanges($invoice, $input);

            // Recalculate totals if line items changed
            if ($this->hasLineItemChanges($input)) {
                $invoice->calculateTotals();
            }

            // Handle status-specific actions
            $this->handleStatusChanges($invoice, $updateData, $input);
        });
    }

    /**
     * Handle line item additions, removals, and updates.
     */
    protected function handleLineItemChanges(Invoice $invoice, array $input): void
    {
        // Add new line items
        if (! empty($input['add_items'])) {
            $this->addLineItems($invoice, $input['add_items']);
        }

        // Remove line items
        if (! empty($input['remove_items'])) {
            $this->removeLineItems($invoice, $input['remove_items']);
        }

        // Update existing line items
        if (! empty($input['update_items'])) {
            $this->updateLineItems($invoice, $input['update_items']);
        }
    }

    /**
     * Add new line items to invoice.
     */
    protected function addLineItems(Invoice $invoice, array $items): void
    {
        foreach ($items as $itemData) {
            $normalizedItem = $this->normalizeItemData($itemData);
            $this->invoiceService->addLineItem($invoice, $normalizedItem);
        }
    }

    /**
     * Remove line items from invoice.
     */
    protected function removeLineItems(Invoice $invoice, array $itemIds): void
    {
        $ids = explode(',', $itemIds);
        $invoice->lineItems()->whereIn('id', $ids)->delete();
    }

    /**
     * Update existing line items.
     */
    protected function updateLineItems(Invoice $invoice, array $items): void
    {
        foreach ($items as $itemData) {
            if (! isset($itemData['id'])) {
                continue;
            }

            $lineItem = $invoice->lineItems()->find($itemData['id']);
            if (! $lineItem) {
                continue;
            }

            $normalizedItem = $this->normalizeItemData($itemData);
            $lineItem->update($normalizedItem);
        }
    }

    /**
     * Normalize item data.
     */
    protected function normalizeItemData(array $itemData): array
    {
        return [
            'description' => $itemData['description'] ?? $itemData['name'] ?? 'Unknown Item',
            'quantity' => (float) ($itemData['quantity'] ?? 1),
            'unit_price' => (float) ($itemData['unit_price'] ?? $itemData['price'] ?? 0),
            'tax_rate' => (float) ($itemData['tax_rate'] ?? 0),
            'discount_amount' => (float) ($itemData['discount_amount'] ?? 0),
        ];
    }

    /**
     * Handle status-specific actions.
     */
    protected function handleStatusChanges(Invoice $invoice, array $updateData, array $input): void
    {
        // Mark as sent if status changed to sent
        if (isset($updateData['status']) && $updateData['status'] === 'sent' && $invoice->status === 'sent') {
            $invoice->markAsSent();
        }

        // Mark as paid if status changed to paid
        if (isset($updateData['status']) && $updateData['status'] === 'paid') {
            $invoice->markAsPaid();
        }

        // Handle flags from natural language
        if (in_array('send', $input['flags'] ?? [])) {
            $this->sendInvoice($invoice);
        }

        if (in_array('post', $input['flags'] ?? [])) {
            $this->postInvoice($invoice);
        }
    }

    /**
     * Send invoice to customer.
     */
    protected function sendInvoice(Invoice $invoice): void
    {
        try {
            $invoice->markAsSent();
            $this->info("Invoice #{$invoice->invoice_number} sent to customer.");
        } catch (\Throwable $exception) {
            $this->warning("Failed to send invoice: {$exception->getMessage()}");
        }
    }

    /**
     * Post invoice to ledger.
     */
    protected function postInvoice(Invoice $invoice): void
    {
        try {
            $invoice->update(['status' => 'posted']);
            $this->info("Invoice #{$invoice->invoice_number} posted to ledger.");
        } catch (\Throwable $exception) {
            $this->warning("Failed to post invoice to ledger: {$exception->getMessage()}");
        }
    }

    /**
     * Display update results.
     */
    protected function displayResults(Invoice $invoice, array $input): void
    {
        if ($this->option('quiet')) {
            return;
        }

        $this->displaySuccess('Invoice updated successfully', [
            'Invoice Number' => $invoice->invoice_number,
            'Customer' => $invoice->customer->name,
            'Status' => $invoice->status,
            'Payment Status' => $invoice->payment_status,
            'Total Amount' => "\${$invoice->total_amount}",
            'Balance Due' => "\${$invoice->balance_due}",
        ]);

        // Show updated line items if they changed
        if ($this->hasLineItemChanges($input) && (! $this->option('format') || $this->getOutputFormat() === 'table')) {
            $this->line('');
            $this->info('Updated Line Items:');

            $itemsData = $invoice->lineItems->map(function ($item) {
                return [
                    'ID' => $item->id,
                    'Description' => $item->description,
                    'Quantity' => $item->quantity,
                    'Unit Price' => "\${$item->unit_price}",
                    'Total' => "\${$item->total}",
                ];
            })->toArray();

            $this->formatOutput($itemsData, 'table');
        }
    }

    /**
     * Get usage examples for this command.
     */
    protected function getExamples(): array
    {
        return [
            'invoice:update INV-2024-1 --customer=456 --due-date=2024-02-15',
            'invoice:update 12345 --status=sent --notes="Updated terms and conditions"',
            'invoice:update "INV-2024-001" --add-items=\'[{"description":"Additional Service","quantity":1,"unit_price":500}]\'',
            'invoice:update uuid-string --remove-items=1,2,3 --force',
            'invoice:update --natural="update invoice INV-2024-001 change due date to 30 days from now send" --company=789',
        ];
    }
}
