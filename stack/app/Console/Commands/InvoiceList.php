<?php

namespace App\Console\Commands;

use App\Models\Invoice;

class InvoiceList extends InvoiceBaseCommand
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'invoice:list
                           {--customer= : Filter by customer ID or name}
                           {--status= : Filter by status (draft, sent, posted, paid, cancelled, overdue)}
                           {--payment-status= : Filter by payment status (unpaid, partial, paid, overdue)}
                           {--date-from= : Filter invoices from date (Y-m-d format)}
                           {--date-to= : Filter invoices to date (Y-m-d format)}
                           {--amount-min= : Minimum amount filter}
                           {--amount-max= : Maximum amount filter}
                           {--search= : Search in invoice number, customer name, or notes}
                           {--sort=created_at : Sort field (created_at, issue_date, due_date, total_amount)}
                           {--order=desc : Sort order (asc, desc)}
                           {--per-page=25 : Results per page (0 for all)}
                           {--page=1 : Page number for pagination}
                           {--overdue : Show only overdue invoices}
                           {--unpaid : Show only unpaid invoices}
                           {--sent : Show only sent invoices}
                           {--posted : Show only posted invoices}
                           {--draft : Show only draft invoices}
                           {--summarize : Show summary statistics instead of detailed list}
                           {--export= : Export to file (format: csv, json, xlsx)}
                           {--company= : Company ID (overrides current company)}
                           {--format=table : Output format (table, json, csv, text)}
                           {--natural= : Natural language input}
                           {--quiet : Suppress output}';

    /**
     * The console command description.
     */
    protected $description = 'List and filter invoices';

    /**
     * Execute the command logic.
     */
    protected function executeCommand(): int
    {
        $input = $this->parseInput();

        // Build query
        $query = $this->buildInvoiceQuery($input);

        // Get summary if requested
        if ($this->option('summarize') || in_array('summary', $input['flags'] ?? [])) {
            return $this->showInvoiceSummary($query, $input);
        }

        // Get invoices
        $invoices = $this->getInvoices($query, $input);

        // Export if requested
        if ($exportPath = $this->option('export') ?? $input['export'] ?? null) {
            return $this->exportInvoices($invoices, $exportPath, $input);
        }

        // Display results
        $this->displayInvoiceList($invoices, $input);

        return self::SUCCESS;
    }

    /**
     * Build the invoice query based on filters.
     */
    protected function buildInvoiceQuery(array $input)
    {
        $query = Invoice::where('company_id', $this->company->id)
            ->with(['customer', 'lineItems', 'payments']);

        // Customer filter
        if (isset($input['customer']) || $this->option('customer')) {
            $customerFilter = $input['customer'] ?? $this->option('customer');

            // Try by UUID first
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $customerFilter)) {
                $query->where('customer_id', $customerFilter);
            } else {
                // Search by customer name
                $query->whereHas('customer', function ($q) use ($customerFilter) {
                    $q->where('name', 'LIKE', "%{$customerFilter}%");
                });
            }
        }

        // Status filters
        if (isset($input['status']) || $this->option('status')) {
            $status = $input['status'] ?? $this->option('status');
            if ($status === 'overdue') {
                $query->overdue();
            } else {
                $query->where('status', $status);
            }
        }

        if (isset($input['payment_status']) || $this->option('payment-status')) {
            $query->where('payment_status', $input['payment_status'] ?? $this->option('payment-status'));
        }

        // Quick status filters
        if ($this->option('overdue')) {
            $query->overdue();
        }

        if ($this->option('unpaid')) {
            $query->where('payment_status', 'unpaid');
        }

        if ($this->option('sent')) {
            $query->where('status', 'sent');
        }

        if ($this->option('posted')) {
            $query->where('status', 'posted');
        }

        if ($this->option('draft')) {
            $query->draft();
        }

        // Date filters
        if ($dateFrom = $input['date_from'] ?? $this->option('date-from')) {
            $query->whereDate('issue_date', '>=', $dateFrom);
        }

        if ($dateTo = $input['date_to'] ?? $this->option('date-to')) {
            $query->whereDate('issue_date', '<=', $dateTo);
        }

        // Amount filters
        if ($amountMin = $input['amount_min'] ?? $this->option('amount-min')) {
            $query->where('total_amount', '>=', (float) $amountMin);
        }

        if ($amountMax = $input['amount_max'] ?? $this->option('amount-max')) {
            $query->where('total_amount', '<=', (float) $amountMax);
        }

        // Search filter
        if ($search = $input['search'] ?? $this->option('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'LIKE', "%{$search}%")
                    ->orWhere('notes', 'LIKE', "%{$search}%")
                    ->orWhereHas('customer', function ($subQ) use ($search) {
                        $subQ->where('name', 'LIKE', "%{$search}%");
                    });
            });
        }

        // Sorting
        $sortField = $input['sort'] ?? $this->option('sort') ?? 'created_at';
        $sortOrder = $input['order'] ?? $this->option('order') ?? 'desc';

        $allowedSortFields = ['created_at', 'issue_date', 'due_date', 'total_amount', 'invoice_number'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortOrder);
        }

        return $query;
    }

    /**
     * Get invoices based on query and pagination.
     */
    protected function getInvoices($query, array $input)
    {
        $perPage = (int) ($input['per_page'] ?? $this->option('per-page') ?? 25);
        $page = (int) ($input['page'] ?? $this->option('page') ?? 1);

        if ($perPage === 0) {
            // Get all results
            return $query->get();
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Display invoice list.
     */
    protected function displayInvoiceList($invoices, array $input): void
    {
        if ($invoices->isEmpty()) {
            $this->info('No invoices found matching your criteria.');

            return;
        }

        $outputFormat = $this->getOutputFormat();

        if ($outputFormat === 'table') {
            $this->displayAsTable($invoices, $input);
        } else {
            $this->displayAsStructured($invoices, $outputFormat, $input);
        }

        // Show pagination info if applicable
        if (method_exists($invoices, 'links') && $invoices->hasPages()) {
            $this->line('');
            $this->info("Page {$invoices->currentPage()} of {$invoices->lastPage()} ({$invoices->total()} total invoices)");
        }
    }

    /**
     * Display invoices as table.
     */
    protected function displayAsTable($invoices, array $input): void
    {
        $headers = [
            'Invoice #',
            'Customer',
            'Issue Date',
            'Due Date',
            'Total',
            'Balance',
            'Status',
            'Payment',
        ];

        $rows = $invoices->map(function ($invoice) {
            return [
                $invoice->invoice_number,
                $invoice->customer->name,
                $invoice->issue_date->format('Y-m-d'),
                $invoice->due_date->format('Y-m-d'),
                "\${$invoice->total_amount}",
                "\${$invoice->balance_due}",
                $invoice->status,
                $invoice->payment_status,
            ];
        })->toArray();

        $this->table($headers, $rows);

        // Show summary statistics
        $this->showQuickSummary($invoices);
    }

    /**
     * Display invoices as structured data.
     */
    protected function displayAsStructured($invoices, string $format, array $input): void
    {
        $data = $invoices->map(function ($invoice) {
            return [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'customer' => [
                    'id' => $invoice->customer->id,
                    'name' => $invoice->customer->name,
                    'email' => $invoice->customer->email,
                ],
                'dates' => [
                    'issue_date' => $invoice->issue_date->format('Y-m-d'),
                    'due_date' => $invoice->due_date->format('Y-m-d'),
                    'sent_at' => $invoice->sent_at?->format('Y-m-d H:i:s'),
                    'paid_at' => $invoice->paid_at?->format('Y-m-d H:i:s'),
                ],
                'amounts' => [
                    'subtotal' => $invoice->subtotal,
                    'tax_amount' => $invoice->tax_amount,
                    'discount_amount' => $invoice->discount_amount,
                    'total_amount' => $invoice->total_amount,
                    'balance_due' => $invoice->balance_due,
                ],
                'status' => $invoice->status,
                'payment_status' => $invoice->payment_status,
                'created_at' => $invoice->created_at->format('Y-m-d H:i:s'),
            ];
        })->toArray();

        $this->formatOutput($data, $format);
    }

    /**
     * Show quick summary statistics.
     */
    protected function showQuickSummary($invoices): void
    {
        if ($this->option('quiet')) {
            return;
        }

        $totalAmount = $invoices->sum('total_amount');
        $totalBalance = $invoices->sum('balance_due');
        $overdueCount = $invoices->filter(fn ($invoice) => $invoice->is_overdue)->count();
        $unpaidCount = $invoices->filter(fn ($invoice) => $invoice->payment_status === 'unpaid')->count();

        $this->line('');
        $this->info('Summary:');
        $this->line("Total Invoices: {$invoices->count()}");
        $this->line("Total Amount: \${$totalAmount}");
        $this->line("Total Balance Due: \${$totalBalance}");
        $this->line("Overdue: {$overdueCount}");
        $this->line("Unpaid: {$unpaidCount}");
    }

    /**
     * Show invoice summary statistics.
     */
    protected function showInvoiceSummary($query, array $input): int
    {
        $invoices = $query->get();

        if ($invoices->isEmpty()) {
            $this->info('No invoices found matching your criteria.');

            return self::SUCCESS;
        }

        // Calculate statistics
        $stats = [
            'total_invoices' => $invoices->count(),
            'total_amount' => $invoices->sum('total_amount'),
            'total_balance_due' => $invoices->sum('balance_due'),
            'average_amount' => $invoices->avg('total_amount'),
            'overdue_count' => $invoices->filter(fn ($invoice) => $invoice->is_overdue)->count(),
            'overdue_amount' => $invoices->filter(fn ($invoice) => $invoice->is_overdue)->sum('balance_due'),
        ];

        // Status breakdown
        $statusBreakdown = $invoices->groupBy('status')->map(function ($group) {
            return [
                'count' => $group->count(),
                'total_amount' => $group->sum('total_amount'),
                'balance_due' => $group->sum('balance_due'),
            ];
        })->toArray();

        // Payment status breakdown
        $paymentBreakdown = $invoices->groupBy('payment_status')->map(function ($group) {
            return [
                'count' => $group->count(),
                'total_amount' => $group->sum('total_amount'),
                'balance_due' => $group->sum('balance_due'),
            ];
        })->toArray();

        $this->info('Invoice Summary Statistics');
        $this->line('');

        // Overall stats
        $this->info('Overall Statistics:');
        $this->displaySuccess('Summary', [
            'Total Invoices' => $stats['total_invoices'],
            'Total Amount' => "\${$stats['total_amount']}",
            'Balance Due' => "\${$stats['total_balance_due']}",
            'Average Amount' => "\${$stats['average_amount']}",
            'Overdue Count' => $stats['overdue_count'],
            'Overdue Amount' => "\${$stats['overdue_amount']}",
        ]);

        // Status breakdown
        $this->line('');
        $this->info('Status Breakdown:');
        foreach ($statusBreakdown as $status => $data) {
            $this->line("  {$status}: {$data['count']} invoices, \${$data['total_amount']} total, \${$data['balance_due']} due");
        }

        // Payment status breakdown
        $this->line('');
        $this->info('Payment Status Breakdown:');
        foreach ($paymentBreakdown as $status => $data) {
            $this->line("  {$status}: {$data['count']} invoices, \${$data['total_amount']} total, \${$data['balance_due']} due");
        }

        return self::SUCCESS;
    }

    /**
     * Export invoices to file.
     */
    protected function exportInvoices($invoices, string $exportPath, array $input): int
    {
        $format = pathinfo($exportPath, PATHINFO_EXTENSION);

        if (! in_array($format, ['csv', 'json', 'xlsx'])) {
            $this->error("Unsupported export format: {$format}. Use csv, json, or xlsx.");

            return self::FAILURE;
        }

        try {
            $data = $this->prepareExportData($invoices);

            switch ($format) {
                case 'csv':
                    $this->exportToCsv($data, $exportPath);
                    break;
                case 'json':
                    $this->exportToJson($data, $exportPath);
                    break;
                case 'xlsx':
                    $this->exportToXlsx($data, $exportPath);
                    break;
            }

            $this->info("Exported {$invoices->count()} invoices to {$exportPath}");

            return self::SUCCESS;

        } catch (\Throwable $exception) {
            $this->error('Export failed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Prepare data for export.
     */
    protected function prepareExportData($invoices): array
    {
        return $invoices->map(function ($invoice) {
            return [
                'invoice_number' => $invoice->invoice_number,
                'customer_name' => $invoice->customer->name,
                'customer_email' => $invoice->customer->email,
                'issue_date' => $invoice->issue_date->format('Y-m-d'),
                'due_date' => $invoice->due_date->format('Y-m-d'),
                'status' => $invoice->status,
                'payment_status' => $invoice->payment_status,
                'subtotal' => $invoice->subtotal,
                'tax_amount' => $invoice->tax_amount,
                'discount_amount' => $invoice->discount_amount,
                'total_amount' => $invoice->total_amount,
                'balance_due' => $invoice->balance_due,
                'notes' => $invoice->notes,
                'created_at' => $invoice->created_at->format('Y-m-d H:i:s'),
            ];
        })->toArray();
    }

    /**
     * Export to CSV.
     */
    protected function exportToCsv(array $data, string $path): void
    {
        $file = fopen($path, 'w');

        if (! empty($data)) {
            fputcsv($file, array_keys($data[0]));
            foreach ($data as $row) {
                fputcsv($file, $row);
            }
        }

        fclose($file);
    }

    /**
     * Export to JSON.
     */
    protected function exportToJson(array $data, string $path): void
    {
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * Export to XLSX (simplified implementation).
     */
    protected function exportToXlsx(array $data, string $path): void
    {
        // In a real implementation, this would use a library like Laravel Excel
        // For now, we'll export as CSV with .xlsx extension as placeholder
        $this->warn('XLSX export not fully implemented. Exporting as CSV instead.');
        $this->exportToCsv($data, str_replace('.xlsx', '.csv', $path));
    }

    /**
     * Get usage examples for this command.
     */
    protected function getExamples(): array
    {
        return [
            'invoice:list',
            'invoice:list --status=unpaid --overdue',
            'invoice:list --customer="ACME Corp" --sort=due_date --order=asc',
            'invoice:list --date-from=2024-01-01 --date-to=2024-01-31 --summarize',
            'invoice:list --amount-min=1000 --amount-max=5000 --per-page=10',
            'invoice:list --search="consulting" --export=report.csv',
            'invoice:list --natural="show unpaid invoices from ACME Corp over $1000" --format=json',
        ];
    }
}
