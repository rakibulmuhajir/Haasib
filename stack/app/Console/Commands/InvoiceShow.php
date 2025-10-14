<?php

namespace App\Console\Commands;

use App\Models\Invoice;

class InvoiceShow extends InvoiceBaseCommand
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'invoice:show
                           {invoice : Invoice ID, number, or UUID}
                           {--with-payments : Include payment history}
                           {--with-line-items : Include detailed line items (default)}
                           {--with-attachments : Include attachment information}
                           {--with-activity : Include activity log}
                           {--with-ledger : Include ledger entry information}
                           {--line-items-format=table : Line items format (table, json, csv)}
                           {--payments-format=table : Payments format (table, json, csv)}
                           {--calculate-taxes : Show tax calculation breakdown}
                           {--show-balances : Show running balance calculations}
                           {--company= : Company ID (overrides current company)}
                           {--format=table : Output format (table, json, csv, text)}
                           {--natural= : Natural language input}
                           {--quiet : Suppress output}';

    /**
     * The console command description.
     */
    protected $description = 'Show detailed information about an invoice';

    /**
     * Execute the command logic.
     */
    protected function executeCommand(): int
    {
        $input = $this->parseInput();

        // Find the invoice
        $invoice = $this->findInvoice($input);

        // Load relationships based on options
        $this->loadInvoiceRelationships($invoice, $input);

        // Display invoice information
        $this->displayInvoiceDetails($invoice, $input);

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

        // Try numeric ID
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
     * Load invoice relationships based on options.
     */
    protected function loadInvoiceRelationships(Invoice $invoice, array $input): void
    {
        // Always load customer
        $invoice->load('customer');

        // Load line items by default or if requested
        if ($this->option('with-line-items') || ! isset($input['with_line_items'])) {
            $invoice->load('lineItems');
        }

        // Load payments if requested
        if ($this->option('with-payments') || isset($input['with_payments'])) {
            $invoice->load('payments');
        }

        // Load creator
        $invoice->load('creator');
    }

    /**
     * Display comprehensive invoice details.
     */
    protected function displayInvoiceDetails(Invoice $invoice, array $input): void
    {
        $outputFormat = $this->getOutputFormat();

        if ($outputFormat === 'table') {
            $this->displayAsStructuredView($invoice, $input);
        } else {
            $this->displayAsStructuredData($invoice, $outputFormat, $input);
        }
    }

    /**
     * Display invoice in structured view (table format).
     */
    protected function displayAsStructuredView(Invoice $invoice, array $input): void
    {
        // Invoice Header
        $this->displayInvoiceHeader($invoice);

        // Customer Information
        $this->displayCustomerInfo($invoice);

        // Financial Information
        $this->displayFinancialInfo($invoice);

        // Line Items
        if ($invoice->relationLoaded('lineItems') && ! $invoice->lineItems->isEmpty()) {
            $this->displayLineItems($invoice, $input);
        }

        // Payment History
        if ($invoice->relationLoaded('payments') && ! $invoice->payments->isEmpty()) {
            $this->displayPaymentHistory($invoice, $input);
        }

        // Status and Dates
        $this->displayStatusAndDates($invoice);

        // Tax breakdown if requested
        if ($this->option('calculate-taxes')) {
            $this->displayTaxBreakdown($invoice);
        }

        // Balance calculations if requested
        if ($this->option('show-balances')) {
            $this->displayBalanceCalculations($invoice);
        }
    }

    /**
     * Display invoice header information.
     */
    protected function displayInvoiceHeader(Invoice $invoice): void
    {
        $this->info('Invoice Details');
        $this->str_repeat('=', 50);
        $this->line('');

        $headerData = [
            'Invoice Number' => $invoice->invoice_number,
            'Status' => $this->formatStatus($invoice->status),
            'Payment Status' => $this->formatPaymentStatus($invoice->payment_status),
            'Currency' => $invoice->currency,
            'Created By' => $invoice->creator?->name ?? 'Unknown',
        ];

        foreach ($headerData as $label => $value) {
            $this->line("{$label}: {$value}");
        }

        $this->line('');
    }

    /**
     * Display customer information.
     */
    protected function displayCustomerInfo(Invoice $invoice): void
    {
        $this->info('Customer Information');
        $this->str_repeat('-', 18);
        $this->line('');

        $customerData = [
            'Name' => $invoice->customer->name,
            'Email' => $invoice->customer->email ?? 'N/A',
            'Phone' => $invoice->customer->phone ?? 'N/A',
        ];

        foreach ($customerData as $label => $value) {
            $this->line("{$label}: {$value}");
        }

        $this->line('');
    }

    /**
     * Display financial information.
     */
    protected function displayFinancialInfo(Invoice $invoice): void
    {
        $this->info('Financial Information');
        $this->str_repeat('-', 21);
        $this->line('');

        $financialData = [
            'Subtotal' => "\${$invoice->subtotal}",
            'Tax Amount' => "\${$invoice->tax_amount}",
            'Discount Amount' => "\${$invoice->discount_amount}",
            'Total Amount' => "\${$invoice->total_amount}",
            'Balance Due' => "\${$invoice->balance_due}",
        ];

        foreach ($financialData as $label => $value) {
            $this->line("{$label}: {$value}");
        }

        $this->line('');
    }

    /**
     * Display line items.
     */
    protected function displayLineItems(Invoice $invoice, array $input): void
    {
        $this->info('Line Items');
        $this->str_repeat('-', 11);
        $this->line('');

        $format = $input['line_items_format'] ?? $this->option('line-items-format') ?? 'table';

        if ($format === 'table') {
            $headers = ['Description', 'Quantity', 'Unit Price', 'Tax Rate', 'Discount', 'Total'];
            $rows = $invoice->lineItems->map(function ($item) {
                return [
                    $item->description,
                    $item->quantity,
                    "\${$item->unit_price}",
                    "{$item->tax_rate}%",
                    "\${$item->discount_amount}",
                    "\${$item->total}",
                ];
            })->toArray();

            $this->table($headers, $rows);
        } else {
            $this->formatOutput($invoice->lineItems->toArray(), $format);
        }

        $this->line('');
    }

    /**
     * Display payment history.
     */
    protected function displayPaymentHistory(Invoice $invoice, array $input): void
    {
        $this->info('Payment History');
        $this->str_repeat('-', 16);
        $this->line('');

        $format = $input['payments_format'] ?? $this->option('payments-format') ?? 'table';

        if ($format === 'table') {
            $headers = ['Date', 'Amount', 'Method', 'Reference', 'Status'];
            $rows = $invoice->payments->map(function ($payment) {
                return [
                    $payment->payment_date->format('Y-m-d'),
                    "\${$payment->amount}",
                    $payment->payment_method ?? 'N/A',
                    $payment->reference ?? 'N/A',
                    $payment->status ?? 'completed',
                ];
            })->toArray();

            $this->table($headers, $rows);
        } else {
            $this->formatOutput($invoice->payments->toArray(), $format);
        }

        $totalPaid = $invoice->payments->sum('amount');
        $this->line("Total Paid: \${$totalPaid}");
        $this->line('');
    }

    /**
     * Display status and dates.
     */
    protected function displayStatusAndDates(Invoice $invoice): void
    {
        $this->info('Status and Dates');
        $this->str_repeat('-', 17);
        $this->line('');

        $datesData = [
            'Issue Date' => $invoice->issue_date->format('Y-m-d'),
            'Due Date' => $invoice->due_date->format('Y-m-d'),
            'Sent At' => $invoice->sent_at?->format('Y-m-d H:i:s') ?? 'Not sent',
            'Posted At' => $invoice->posted_at?->format('Y-m-d H:i:s') ?? 'Not posted',
            'Paid At' => $invoice->paid_at?->format('Y-m-d H:i:s') ?? 'Unpaid',
        ];

        foreach ($datesData as $label => $value) {
            $this->line("{$label}: {$value}");
        }

        // Additional status information
        if ($invoice->is_overdue) {
            $this->line("Overdue by: {$invoice->days_overdue} days");
        }

        $this->line('');
    }

    /**
     * Display tax breakdown.
     */
    protected function displayTaxBreakdown(Invoice $invoice): void
    {
        $this->info('Tax Breakdown');
        $this->str_repeat('-', 14);
        $this->line('');

        // Group line items by tax rate
        $taxGroups = $invoice->lineItems->groupBy('tax_rate');

        foreach ($taxGroups as $taxRate => $items) {
            $taxableAmount = $items->sum(function ($item) {
                return $item->quantity * $item->unit_price - $item->discount_amount;
            });

            $taxAmount = $items->sum('tax_amount');

            $this->line("Tax Rate: {$taxRate}%");
            $this->line("  Taxable Amount: \${$taxableAmount}");
            $this->line("  Tax Amount: \${$taxAmount}");
            $this->line('');
        }

        $this->line("Total Tax: \${$invoice->tax_amount}");
        $this->line('');
    }

    /**
     * Display balance calculations.
     */
    protected function displayBalanceCalculations(Invoice $invoice): void
    {
        $this->info('Balance Calculations');
        $this->str_repeat('-', 20);
        $this->line('');

        $this->line("Original Amount: \${$invoice->total_amount}");
        $this->line("Total Payments: -\${$invoice->payments->sum('amount')}");
        $this->line("Balance Due: \${$invoice->balance_due}");

        if ($invoice->balance_due > 0) {
            $this->line('');
            $this->line('Days Until Due: '.max(0, now()->diffInDays($invoice->due_date, false)));

            if ($invoice->is_overdue) {
                $this->line("Days Overdue: {$invoice->days_overdue}");
            }
        }

        $this->line('');
    }

    /**
     * Display invoice as structured data.
     */
    protected function displayAsStructuredData(Invoice $invoice, string $format, array $input): void
    {
        $data = [
            'invoice' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'status' => $invoice->status,
                'payment_status' => $invoice->payment_status,
                'currency' => $invoice->currency,
                'dates' => [
                    'issue_date' => $invoice->issue_date->format('Y-m-d'),
                    'due_date' => $invoice->due_date->format('Y-m-d'),
                    'sent_at' => $invoice->sent_at?->format('Y-m-d H:i:s'),
                    'posted_at' => $invoice->posted_at?->format('Y-m-d H:i:s'),
                    'paid_at' => $invoice->paid_at?->format('Y-m-d H:i:s'),
                ],
                'amounts' => [
                    'subtotal' => $invoice->subtotal,
                    'tax_amount' => $invoice->tax_amount,
                    'discount_amount' => $invoice->discount_amount,
                    'total_amount' => $invoice->total_amount,
                    'balance_due' => $invoice->balance_due,
                ],
                'notes' => $invoice->notes,
                'terms' => $invoice->terms,
            ],
            'customer' => [
                'id' => $invoice->customer->id,
                'name' => $invoice->customer->name,
                'email' => $invoice->customer->email,
                'phone' => $invoice->customer->phone,
            ],
        ];

        // Add line items if loaded
        if ($invoice->relationLoaded('lineItems')) {
            $data['line_items'] = $invoice->lineItems->map(function ($item) {
                return [
                    'id' => $item->id,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'tax_rate' => $item->tax_rate,
                    'discount_amount' => $item->discount_amount,
                    'total' => $item->total,
                ];
            })->toArray();
        }

        // Add payments if loaded
        if ($invoice->relationLoaded('payments')) {
            $data['payments'] = $invoice->payments->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'amount' => $payment->amount,
                    'payment_date' => $payment->payment_date->format('Y-m-d'),
                    'payment_method' => $payment->payment_method,
                    'reference' => $payment->reference,
                    'status' => $payment->status,
                ];
            })->toArray();
        }

        $this->formatOutput($data, $format);
    }

    /**
     * Format status with color coding.
     */
    protected function formatStatus(string $status): string
    {
        $statusColors = [
            'draft' => 'gray',
            'sent' => 'blue',
            'posted' => 'green',
            'paid' => 'green',
            'cancelled' => 'red',
        ];

        return strtoupper($status);
    }

    /**
     * Format payment status with indicators.
     */
    protected function formatPaymentStatus(string $status): string
    {
        $statusIcons = [
            'unpaid' => '🔴',
            'partial' => '🟡',
            'paid' => '🟢',
            'overdue' => '🔴',
        ];

        $icon = $statusIcons[$status] ?? '';

        return "{$icon} ".strtoupper($status);
    }

    /**
     * Get usage examples for this command.
     */
    protected function getExamples(): array
    {
        return [
            'invoice:show INV-2024-001',
            'invoice:show 12345 --with-payments --calculate-taxes',
            'invoice:show "uuid-string" --with-line-items --show-balances',
            'invoice:show INV-2024-001 --with-payments --line-items-format=json',
            'invoice:show --natural="show invoice INV-2024-001 with payment details" --format=json',
        ];
    }
}
