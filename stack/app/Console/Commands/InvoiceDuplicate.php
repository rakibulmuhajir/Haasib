<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Modules\Accounting\Services\InvoiceService;

class InvoiceDuplicate extends InvoiceBaseCommand
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'invoice:duplicate
                           {invoice : Source invoice ID, number, or UUID}
                           {--customer= : New customer ID (keeps original if not specified)}
                           {--issue-date= : New issue date (Y-m-d format, defaults to today)}
                           {--due-date= : New due date (Y-m-d format)}
                           {--items= : Override line items (JSON format)}
                           {--update-items= : Update specific line items (JSON format)}
                           {--remove-items= : Remove line items by ID (comma-separated)}
                           {--add-items= : Add additional line items (JSON format)}
                           {--notes= : New invoice notes}
                           {--terms= : New payment terms}
                           {--currency= : New currency code}
                           {--prefix=DUP : Prefix for new invoice number}
                           {--keep-number : Keep original invoice number}
                           {--keep-line-items : Keep original line items by default}
                           {--reset-quantities : Reset all quantities to 0}
                           {--adjust-prices= : Price adjustment percentage (+/-)}
                           {--round-to= : Round amounts to nearest decimal place}
                           {--draft : Create as draft (default)}
                           {--send : Send invoice immediately after creation}
                           {--post : Post invoice to ledger immediately}
                           {--preview : Preview duplicated invoice without creating}
                           {--company= : Company ID (overrides current company)}
                           {--format=table : Output format (table, json, csv, text)}
                           {--natural= : Natural language input}
                           {--quiet : Suppress output}';

    /**
     * The console command description.
     */
    protected $description = 'Duplicate an existing invoice';

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

        // Find the source invoice
        $sourceInvoice = $this->findSourceInvoice($input);

        // Validate that invoice can be duplicated
        if (! $this->canDuplicateInvoice($sourceInvoice)) {
            return self::FAILURE;
        }

        // Prepare duplicated invoice data
        $duplicateData = $this->prepareDuplicateData($sourceInvoice, $input);

        // Prepare line items
        $lineItems = $this->prepareLineItems($sourceInvoice, $input);

        // Preview mode
        if ($this->option('preview') || in_array('preview', $input['flags'] ?? [])) {
            return $this->previewDuplicate($sourceInvoice, $duplicateData, $lineItems);
        }

        // Create the duplicated invoice
        $newInvoice = $this->createDuplicateInvoice($duplicateData, $lineItems);

        // Handle post-creation actions
        $this->handlePostCreationActions($newInvoice, $input);

        // Display results
        $this->displayResults($sourceInvoice, $newInvoice, $input);

        // Log the action
        $this->logExecution('invoice_duplicated', [
            'source_invoice_id' => $sourceInvoice->id,
            'new_invoice_id' => $newInvoice->id,
            'source_number' => $sourceInvoice->invoice_number,
            'new_number' => $newInvoice->invoice_number,
        ]);

        return self::SUCCESS;
    }

    /**
     * Find source invoice by ID, number, or UUID.
     */
    protected function findSourceInvoice(array $input): Invoice
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

        // Try numeric ID
        if (! isset($invoice) && is_numeric($identifier)) {
            $invoice = Invoice::where('id', $identifier)
                ->where('company_id', $this->company->id)
                ->first();
        }

        if (! $invoice) {
            $this->error("Source invoice '{$identifier}' not found.");
            exit(1);
        }

        return $invoice;
    }

    /**
     * Check if invoice can be duplicated.
     */
    protected function canDuplicateInvoice(Invoice $invoice): bool
    {
        // Check if source invoice has line items
        if ($invoice->lineItems->isEmpty()) {
            $this->error("Cannot duplicate invoice #{$invoice->invoice_number} without line items.");

            return false;
        }

        // Check if customer exists
        if (! $invoice->customer) {
            $this->error("Cannot duplicate invoice #{$invoice->invoice_number} without a valid customer.");

            return false;
        }

        return true;
    }

    /**
     * Prepare data for the duplicated invoice.
     */
    protected function prepareDuplicateData(Invoice $sourceInvoice, array $input): array
    {
        return [
            'company_id' => $this->company->id,
            'customer_id' => $this->getCustomerId($sourceInvoice, $input),
            'invoice_number' => $this->generateInvoiceNumber($input),
            'order_number' => $sourceInvoice->order_number,
            'issue_date' => $input['issue_date'] ?? $this->option('issue-date') ?? now()->toDateString(),
            'due_date' => $this->calculateDueDate($sourceInvoice, $input),
            'currency' => $input['currency'] ?? $this->option('currency') ?? $sourceInvoice->currency,
            'status' => $this->determineInitialStatus($input),
            'payment_status' => 'unpaid',
            'notes' => $input['notes'] ?? $this->option('notes') ?? $this->generateNotes($sourceInvoice),
            'terms' => $this->buildPaymentTerms($sourceInvoice, $input),
            'created_by_user_id' => $this->user->id,
        ];
    }

    /**
     * Get customer ID for the duplicated invoice.
     */
    protected function getCustomerId(Invoice $sourceInvoice, array $input): string
    {
        $customer = $input['customer'] ?? $this->option('customer');

        if (! $customer) {
            // Keep original customer
            return $sourceInvoice->customer_id;
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
     * Generate invoice number for duplicate.
     */
    protected function generateInvoiceNumber(array $input): string
    {
        if ($this->option('keep-number')) {
            // Keep original number (should be rare)
            $originalNumber = $input['invoice'] ?? $this->argument('invoice');

            return $originalNumber;
        }

        $prefix = $input['prefix'] ?? $this->option('prefix') ?? 'DUP';
        $year = now()->format('Y');
        $sequence = Invoice::where('company_id', $this->company->id)
            ->whereYear('created_at', $year)
            ->withTrashed()
            ->count() + 1;

        return "{$prefix}-{$year}-{$sequence}";
    }

    /**
     * Calculate due date for the duplicated invoice.
     */
    protected function calculateDueDate(Invoice $sourceInvoice, array $input): string
    {
        if (isset($input['due_date'])) {
            return $input['due_date'];
        }

        $issueDate = $input['issue_date'] ?? $this->option('issue-date') ?? now()->toDateString();

        // Calculate the same number of days as original
        $daysDifference = $sourceInvoice->due_date->diffInDays($sourceInvoice->issue_date);

        return \Carbon\Carbon::parse($issueDate)->addDays($daysDifference)->toDateString();
    }

    /**
     * Determine initial invoice status.
     */
    protected function determineInitialStatus(array $input): string
    {
        if (in_array('send', $input['flags'] ?? [])) {
            return 'sent';
        }

        if (in_array('post', $input['flags'] ?? []) || $this->option('post')) {
            return 'posted';
        }

        return 'draft';
    }

    /**
     * Generate notes for the duplicated invoice.
     */
    protected function generateNotes(Invoice $sourceInvoice): string
    {
        $notes = "Duplicated from invoice #{$sourceInvoice->invoice_number}";

        if ($sourceInvoice->notes) {
            $notes .= "\n\nOriginal notes: {$sourceInvoice->notes}";
        }

        return $notes;
    }

    /**
     * Build payment terms.
     */
    protected function buildPaymentTerms(Invoice $sourceInvoice, array $input): ?string
    {
        if (isset($input['terms'])) {
            return $input['terms'];
        }

        return $sourceInvoice->terms;
    }

    /**
     * Prepare line items for the duplicated invoice.
     */
    protected function prepareLineItems(Invoice $sourceInvoice, array $input): array
    {
        $lineItems = [];

        // Start with original line items if keeping them
        if ($this->option('keep-line-items') || ! isset($input['items'])) {
            $sourceInvoice->load('lineItems');

            foreach ($sourceInvoice->lineItems as $item) {
                $lineItems[] = [
                    'description' => $item->description,
                    'quantity' => $this->option('reset-quantities') ? 0 : $item->quantity,
                    'unit_price' => $item->unit_price,
                    'tax_rate' => $item->tax_rate,
                    'discount_amount' => $item->discount_amount,
                ];
            }
        }

        // Override with new items if provided
        if (! empty($input['items'])) {
            $lineItems = $this->normalizeItemsData($input['items']);
        }

        // Update specific items
        if (! empty($input['update_items'])) {
            $lineItems = $this->updateSpecificItems($lineItems, $input['update_items']);
        }

        // Remove specific items
        if (! empty($input['remove_items'])) {
            $lineItems = $this->removeSpecificItems($lineItems, $input['remove_items']);
        }

        // Add additional items
        if (! empty($input['add_items'])) {
            $additionalItems = $this->normalizeItemsData($input['add_items']);
            $lineItems = array_merge($lineItems, $additionalItems);
        }

        // Apply price adjustments
        if ($priceAdjustment = $input['adjust_prices'] ?? $this->option('adjust-prices')) {
            $lineItems = $this->applyPriceAdjustment($lineItems, (float) $priceAdjustment);
        }

        // Round amounts if specified
        if ($roundTo = $input['round_to'] ?? $this->option('round-to')) {
            $lineItems = $this->roundAmounts($lineItems, (int) $roundTo);
        }

        return $lineItems;
    }

    /**
     * Normalize items data.
     */
    protected function normalizeItemsData(array $items): array
    {
        $normalized = [];

        foreach ($items as $item) {
            $normalized[] = [
                'description' => $item['description'] ?? $item['name'] ?? 'Unknown Item',
                'quantity' => (float) ($item['quantity'] ?? 1),
                'unit_price' => (float) ($item['unit_price'] ?? $item['price'] ?? 0),
                'tax_rate' => (float) ($item['tax_rate'] ?? 0),
                'discount_amount' => (float) ($item['discount_amount'] ?? 0),
            ];
        }

        return $normalized;
    }

    /**
     * Update specific items.
     */
    protected function updateSpecificItems(array $lineItems, array $updateItems): array
    {
        foreach ($updateItems as $update) {
            if (isset($update['index']) && isset($lineItems[$update['index']])) {
                $lineItems[$update['index']] = array_merge($lineItems[$update['index']], $update);
            }
        }

        return $lineItems;
    }

    /**
     * Remove specific items.
     */
    protected function removeSpecificItems(array $lineItems, string $removeIndexes): array
    {
        $indexes = array_map('intval', explode(',', $removeIndexes));
        rsort($indexes); // Sort in descending to maintain indices

        foreach ($indexes as $index) {
            if (isset($lineItems[$index])) {
                unset($lineItems[$index]);
            }
        }

        return array_values($lineItems); // Re-index array
    }

    /**
     * Apply price adjustment to all items.
     */
    protected function applyPriceAdjustment(array $lineItems, float $adjustment): array
    {
        foreach ($lineItems as &$item) {
            $item['unit_price'] = $item['unit_price'] * (1 + $adjustment / 100);
        }

        return $lineItems;
    }

    /**
     * Round amounts in line items.
     */
    protected function roundAmounts(array $lineItems, int $decimalPlaces): array
    {
        foreach ($lineItems as &$item) {
            $item['unit_price'] = round($item['unit_price'], $decimalPlaces);
            $item['discount_amount'] = round($item['discount_amount'], $decimalPlaces);
        }

        return $lineItems;
    }

    /**
     * Preview the duplicate invoice.
     */
    protected function previewDuplicate(Invoice $sourceInvoice, array $duplicateData, array $lineItems): int
    {
        $this->info('Duplicate Invoice Preview');
        $this->str_repeat('=', 30);
        $this->line('');

        $this->info('Source Invoice:');
        $this->line("Number: {$sourceInvoice->invoice_number}");
        $this->line("Customer: {$sourceInvoice->customer->name}");
        $this->line("Amount: \${$sourceInvoice->total_amount}");
        $this->line('');

        $this->info('New Invoice Details:');
        $this->line("Number: {$duplicateData['invoice_number']}");
        $this->line('Customer: '.\App\Models\Customer::find($duplicateData['customer_id'])->name);
        $this->line("Issue Date: {$duplicateData['issue_date']}");
        $this->line("Due Date: {$duplicateData['due_date']}");
        $this->line("Currency: {$duplicateData['currency']}");
        $this->line("Status: {$duplicateData['status']}");
        $this->line('');

        $this->info('Line Items:');
        if (empty($lineItems)) {
            $this->line('No line items will be copied.');
        } else {
            $headers = ['Description', 'Quantity', 'Unit Price', 'Tax Rate', 'Total'];
            $rows = array_map(function ($item) {
                $total = ($item['quantity'] * $item['unit_price']) +
                         ($item['quantity'] * $item['unit_price'] * $item['tax_rate'] / 100) -
                         $item['discount_amount'];

                return [
                    $item['description'],
                    $item['quantity'],
                    "\${$item['unit_price']}",
                    "{$item['tax_rate']}%",
                    "\${$total}",
                ];
            }, $lineItems);

            $this->table($headers, $rows);

            $totalAmount = array_sum(array_map(function ($item) {
                return ($item['quantity'] * $item['unit_price']) +
                       ($item['quantity'] * $item['unit_price'] * $item['tax_rate'] / 100) -
                       $item['discount_amount'];
            }, $lineItems));

            $this->line("Estimated Total: \${$totalAmount}");
        }

        $this->line('');
        $this->info('This is a preview. Remove --preview to create the actual invoice.');

        return self::SUCCESS;
    }

    /**
     * Create the duplicated invoice.
     */
    protected function createDuplicateInvoice(array $duplicateData, array $lineItems): Invoice
    {
        return $this->invoiceService->createInvoice($duplicateData, $lineItems);
    }

    /**
     * Handle post-creation actions.
     */
    protected function handlePostCreationActions(Invoice $invoice, array $input): void
    {
        // Send invoice if requested
        if (in_array('send', $input['flags'] ?? []) || $this->option('send')) {
            $this->sendInvoice($invoice);
        }

        // Post to ledger if requested
        if (in_array('post', $input['flags'] ?? []) || $this->option('post')) {
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
     * Display duplication results.
     */
    protected function displayResults(Invoice $sourceInvoice, Invoice $newInvoice, array $input): void
    {
        if ($this->option('quiet')) {
            return;
        }

        $this->displaySuccess('Invoice duplicated successfully', [
            'Source Invoice' => $sourceInvoice->invoice_number,
            'New Invoice' => $newInvoice->invoice_number,
            'Customer' => $newInvoice->customer->name,
            'Total Amount' => "\${$newInvoice->total_amount}",
            'Status' => $newInvoice->status,
        ]);

        // Show line items if requested
        if (! $this->option('format') || $this->getOutputFormat() === 'table') {
            $this->line('');
            $this->info('Duplicated Line Items:');

            $itemsData = $newInvoice->lineItems->map(function ($item) {
                return [
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
            'invoice:duplicate INV-2024-001',
            'invoice:duplicate 12345 --customer=456 --issue-date=2024-02-01',
            'invoice:duplicate "uuid-string" --items=\'[{"description":"New Service","quantity":1,"unit_price":1000}]\'',
            'invoice:duplicate INV-2024-001 --adjust-prices=10 --keep-line-items',
            'invoice:duplicate --natural="duplicate invoice INV-2024-001 for new customer with 5% price increase" --preview',
        ];
    }
}
