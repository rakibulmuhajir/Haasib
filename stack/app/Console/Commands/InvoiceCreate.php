<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Modules\Accounting\Services\InvoiceService;

class InvoiceCreate extends InvoiceBaseCommand
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'invoice:create
                           {--customer= : Customer ID or name}
                           {--issue-date= : Issue date (Y-m-d format, defaults to today)}
                           {--due-date= : Due date (Y-m-d format)}
                           {--currency=USD : Currency code}
                           {--notes= : Invoice notes}
                           {--terms= : Payment terms}
                           {--items= : Line items (JSON format or comma-separated)}
                           {--draft : Create as draft (default)}
                           {--send : Send invoice immediately after creation}
                           {--post : Post invoice to ledger immediately}
                           {--company= : Company ID (overrides current company)}
                           {--format=table : Output format (table, json, csv, text)}
                           {--natural= : Natural language input}
                           {--quiet : Suppress output}';

    /**
     * The console command description.
     */
    protected $description = 'Create a new invoice';

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

        // Build invoice data
        $invoiceData = $this->buildInvoiceData($input);

        // Validate line items
        $lineItems = $this->parseLineItems($input);

        if (empty($lineItems)) {
            $this->error('Invoice must have at least one line item.');

            return self::FAILURE;
        }

        // Create the invoice
        $invoice = $this->invoiceService->createInvoice($invoiceData, $lineItems);

        // Handle post-creation actions
        $this->handlePostCreationActions($invoice, $input);

        // Display results
        $this->displayResults($invoice, $input);

        // Log the action
        $this->logExecution('invoice_created', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'total_amount' => $invoice->total_amount,
        ]);

        return self::SUCCESS;
    }

    /**
     * Build invoice data from input.
     */
    protected function buildInvoiceData(array $input): array
    {
        return [
            'company_id' => $this->company->id,
            'customer_id' => $this->getCustomerId($input),
            'invoice_number' => $this->generateInvoiceNumber(),
            'issue_date' => $input['issue_date'] ?? now()->toDateString(),
            'due_date' => $this->calculateDueDate($input),
            'currency' => $input['currency'] ?? $this->company->base_currency ?? 'USD',
            'status' => $this->determineInitialStatus($input),
            'notes' => $input['notes'] ?? null,
            'terms' => $this->buildPaymentTerms($input),
            'created_by_user_id' => $this->user->id,
        ];
    }

    /**
     * Get customer ID from input.
     */
    protected function getCustomerId(array $input): string
    {
        $customer = $input['customer'] ?? $this->option('customer');

        if (! $customer) {
            $customer = $this->ask('Customer ID or name');
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
     * Generate invoice number.
     */
    protected function generateInvoiceNumber(): string
    {
        return Invoice::generateInvoiceNumber($this->company->id);
    }

    /**
     * Calculate due date based on input or payment terms.
     */
    protected function calculateDueDate(array $input): string
    {
        if (isset($input['due_date'])) {
            return $input['due_date'];
        }

        $issueDate = $input['issue_date'] ?? now()->toDateString();
        $paymentTerms = $input['terms']['payment_terms'] ?? 30; // Default 30 days

        return \Carbon\Carbon::parse($issueDate)->addDays($paymentTerms)->toDateString();
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
     * Parse line items from input.
     */
    protected function parseLineItems(array $input): array
    {
        $lineItems = [];

        // From natural language parsing
        if (! empty($input['items'])) {
            foreach ($input['items'] as $item) {
                $lineItems[] = [
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'tax_rate' => 0, // Default tax rate
                    'discount_amount' => 0,
                    'total' => $item['total'],
                ];
            }
        }

        // From --items option
        if ($this->option('items')) {
            $itemsData = $this->parseItemsOption($this->option('items'));
            $lineItems = array_merge($lineItems, $itemsData);
        }

        // Interactive mode if no items provided
        if (empty($lineItems) && ! $this->option('quiet')) {
            $lineItems = $this->collectLineItemsInteractively();
        }

        return $lineItems;
    }

    /**
     * Parse items from command line option.
     */
    protected function parseItemsOption(string $itemsData): array
    {
        $lineItems = [];

        // Try JSON first
        if (str_starts_with($itemsData, '{') || str_starts_with($itemsData, '[')) {
            $items = json_decode($itemsData, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $this->normalizeItemsData($items);
            }
        }

        // Parse comma-separated format: "Description1:Qty1:Price1,Description2:Qty2:Price2"
        $items = explode(',', $itemsData);
        foreach ($items as $item) {
            $parts = explode(':', trim($item));
            if (count($parts) >= 3) {
                $lineItems[] = [
                    'description' => trim($parts[0]),
                    'quantity' => (float) trim($parts[1]),
                    'unit_price' => (float) trim($parts[2]),
                    'tax_rate' => 0,
                    'discount_amount' => 0,
                    'total' => (float) trim($parts[1]) * (float) trim($parts[2]),
                ];
            }
        }

        return $lineItems;
    }

    /**
     * Normalize items data to expected format.
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
                'total' => (float) ($item['total'] ??
                    ((float) ($item['quantity'] ?? 1) * (float) ($item['unit_price'] ?? 0))),
            ];
        }

        return $normalized;
    }

    /**
     * Collect line items interactively from user.
     */
    protected function collectLineItemsInteractively(): array
    {
        $lineItems = [];
        $this->info('Adding line items (press Enter with empty description to finish):');

        while (true) {
            $description = $this->ask('Item description');
            if (empty($description)) {
                break;
            }

            $quantity = (float) $this->ask('Quantity', 1);
            $unitPrice = (float) $this->ask('Unit price', 0);
            $taxRate = (float) $this->ask('Tax rate (%)', 0);
            $discountAmount = (float) $this->ask('Discount amount', 0);

            $total = ($quantity * $unitPrice) + ($quantity * $unitPrice * $taxRate / 100) - $discountAmount;

            $lineItems[] = [
                'description' => $description,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'tax_rate' => $taxRate,
                'discount_amount' => $discountAmount,
                'total' => $total,
            ];

            $this->line("Added: {$description} x {$quantity} @ \${$unitPrice} = \${$total}");
            $this->line('');
        }

        return $lineItems;
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
            // This would integrate with the ledger system
            $invoice->update(['status' => 'posted']);
            $this->info("Invoice #{$invoice->invoice_number} posted to ledger.");
        } catch (\Throwable $exception) {
            $this->warning("Failed to post invoice to ledger: {$exception->getMessage()}");
        }
    }

    /**
     * Display creation results.
     */
    protected function displayResults(Invoice $invoice, array $input): void
    {
        if ($this->option('quiet')) {
            return;
        }

        $this->displaySuccess('Invoice created successfully', [
            'Invoice Number' => $invoice->invoice_number,
            'Customer' => $invoice->customer->name,
            'Issue Date' => $invoice->issue_date->format('Y-m-d'),
            'Due Date' => $invoice->due_date->format('Y-m-d'),
            'Total Amount' => "\${$invoice->total_amount}",
            'Status' => $invoice->status,
        ]);

        // Show line items
        if (! $this->option('format') || $this->getOutputFormat() === 'table') {
            $this->line('');
            $this->info('Line Items:');

            $itemsData = $invoice->lineItems->map(function ($item) {
                return [
                    'Description' => $item->description,
                    'Quantity' => $item->quantity,
                    'Unit Price' => "\${$item->unit_price}",
                    'Tax Rate' => "{$item->tax_rate}%",
                    'Discount' => "\${$item->discount_amount}",
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
            'invoice:create --customer=123 --items="Consulting Services:10:150.00" --send',
            'invoice:create --customer="ACME Corp" --due-date=2024-02-15 --items=\'[{"description":"Website Design","quantity":1,"unit_price":2500}]\'',
            'invoice:create --natural="create invoice for ACME Corp for website design $2500 due in 30 days send" --company=456',
            'invoice:create --customer=789 --issue-date=2024-01-15 --terms="Payment due in 15 days. Late fee: 5%."',
        ];
    }
}
