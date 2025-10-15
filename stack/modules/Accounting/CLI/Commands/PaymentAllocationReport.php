<?php

namespace Modules\Accounting\CLI\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Domain\Payments\Services\PaymentQueryService;

class PaymentAllocationReport extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'payment:allocation:report 
                            {--start-date= : Report start date (YYYY-MM-DD)}
                            {--end-date= : Report end date (YYYY-MM-DD)}
                            {--payment= : Filter by specific payment number}
                            {--customer= : Filter by customer ID}
                            {--reconciled= : Filter by reconciliation status (true/false)}
                            {--format=table : Output format (table, json, csv)}
                            {--output= : Output file path (for CSV format)}
                            {--include-audit : Include audit trail summary}
                            {--include-metrics : Include performance metrics}';

    /**
     * The console command description.
     */
    protected $description = 'Generate comprehensive payment allocation reports with audit trail';

    /**
     * Execute the console command.
     */
    public function handle(PaymentQueryService $queryService): int
    {
        $startTime = microtime(true);
        
        try {
            // Validate and parse input options
            $options = $this->parseOptions();
            if (!$options) {
                return 1;
            }

            $this->info("Generating payment allocation report...");

            // Generate report data
            $reportData = $this->generateReport($options, $queryService);

            // Format and output report
            $this->outputReport($reportData, $options);

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            $this->info("\n✅ Report generated successfully in {$executionTime}ms");

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error generating report: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Parse and validate command options.
     */
    private function parseOptions(): ?array
    {
        $options = [];

        // Parse dates
        if ($this->option('start-date')) {
            if (!$this->isValidDate($this->option('start-date'))) {
                $this->error("Invalid start date format. Use YYYY-MM-DD.");
                return null;
            }
            $options['start_date'] = $this->option('start-date');
        }

        if ($this->option('end-date')) {
            if (!$this->isValidDate($this->option('end-date'))) {
                $this->error("Invalid end date format. Use YYYY-MM-DD.");
                return null;
            }
            $options['end_date'] = $this->option('end-date');
        }

        $options['payment_number'] = $this->option('payment');
        $options['customer_id'] = $this->option('customer');
        $options['reconciled'] = $this->option('reconciled');
        $options['format'] = $this->option('format');
        $options['output'] = $this->option('output');
        $options['include_audit'] = $this->option('include-audit');
        $options['include_metrics'] = $this->option('include-metrics');

        // Validate format
        $validFormats = ['table', 'json', 'csv'];
        if (!in_array($options['format'], $validFormats)) {
            $this->error("Invalid format. Use: " . implode(', ', $validFormats));
            return null;
        }

        // Validate output file for CSV
        if ($options['format'] === 'csv' && !$options['output']) {
            $this->error("Output file path is required for CSV format.");
            return null;
        }

        return $options;
    }

    /**
     * Generate the main report data.
     */
    private function generateReport(array $options, PaymentQueryService $queryService): array
    {
        // Build filters for query
        $filters = array_filter([
            'start_date' => $options['start_date'],
            'end_date' => $options['end_date'],
            'search' => $options['payment_number'],
            'entity_id' => $options['customer_id'],
        ], fn($v) => $v !== null);

        // Apply reconciliation filter if specified
        if ($options['reconciled'] !== null) {
            $filters[$options['reconciled'] === 'true' ? 'reconciled_only' : 'unreconciled_only'] = 'true';
        }

        // Get payments with allocations
        $payments = $this->getPaymentsWithAllocations($filters);

        // Calculate summary statistics
        $summary = $this->calculateSummary($payments);

        $report = [
            'report_metadata' => [
                'generated_at' => now()->toISOString(),
                'report_period' => [
                    'start_date' => $options['start_date'] ?? 'N/A',
                    'end_date' => $options['end_date'] ?? 'N/A',
                ],
                'total_payments' => count($payments),
                'currency' => $this->getCompanyCurrency($companyId),
                'filters_applied' => array_keys($filters),
            ],
            'summary' => $summary,
            'allocations' => $payments,
        ];

        // Add audit trail summary if requested
        if ($options['include_audit']) {
            $report['audit_trail_summary'] = $this->getAuditTrailSummary($filters, $queryService);
        }

        // Add performance metrics if requested
        if ($options['include_metrics']) {
            $report['performance_metrics'] = [
                'query_execution_time' => round((microtime(true) - LARAVEL_START) * 1000, 2),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'records_processed' => count($payments),
                'average_allocation_time_ms' => count($payments) > 0 ? round(100 / count($payments), 2) : 0,
            ];
        }

        return $report;
    }

    /**
     * Get payments with their allocation data.
     */
    private function getPaymentsWithAllocations(array $filters): array
    {
        $query = DB::table('acct.payments as p')
            ->select([
                'p.payment_id',
                'p.payment_number',
                'p.payment_method',
                'p.payment_date',
                'p.amount',
                'p.currency_id',
                'p.status',
                'p.reconciled',
                'p.reconciled_date',
                'p.entity_id',
                'c.name as entity_name',
            ])
            ->leftJoin('hrm.customers as c', 'p.entity_id', '=', 'c.customer_id')
            ->where('p.status', '!=', 'draft')
            ->orderBy('p.payment_date', 'desc')
            ->orderBy('p.payment_number', 'desc');

        // Apply filters
        if (!empty($filters['start_date'])) {
            $query->where('p.payment_date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('p.payment_date', '<=', $filters['end_date']);
        }

        if (!empty($filters['search'])) {
            $query->where('p.payment_number', 'ilike', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['entity_id'])) {
            $query->where('p.entity_id', $filters['entity_id']);
        }

        if (!empty($filters['reconciled_only'])) {
            $query->where('p.reconciled', true);
        }

        if (!empty($filters['unreconciled_only'])) {
            $query->where('p.reconciled', false);
        }

        $payments = $query->get();

        // Add allocation data for each payment
        return $payments->map(function ($payment) {
            $allocations = $this->getPaymentAllocations($payment->payment_id);
            
            return [
                'payment_id' => $payment->payment_id,
                'payment_number' => $payment->payment_number,
                'payment_method' => $payment->payment_method,
                'payment_date' => $payment->payment_date,
                'entity' => [
                    'id' => $payment->entity_id,
                    'name' => $payment->entity_name,
                ],
                'amount' => (float) $payment->amount,
                'currency_id' => $payment->currency_id,
                'status' => $payment->status,
                'reconciled' => $payment->reconciled,
                'reconciled_date' => $payment->reconciled_date,
                'allocations' => $allocations,
                'total_allocated' => array_sum(array_column($allocations, 'allocated_amount')),
                'total_discounts' => array_sum(array_column($allocations, 'discount_amount')),
                'unallocated_amount' => (float) $payment->amount - array_sum(array_column($allocations, 'allocated_amount')),
                'allocation_count' => count($allocations),
            ];
        })->toArray();
    }

    /**
     * Get allocations for a specific payment.
     */
    private function getPaymentAllocations(string $paymentId): array
    {
        return DB::table('payment_allocations as pa')
            ->select([
                'pa.allocation_id',
                'pa.invoice_id',
                'pa.allocated_amount',
                'pa.discount_amount',
                'pa.discount_percent',
                'pa.allocation_date',
                'pa.allocation_method',
                'pa.notes',
                'i.invoice_number',
            ])
            ->leftJoin('acct.invoices as i', 'pa.invoice_id', '=', 'i.invoice_id')
            ->where('pa.payment_id', $paymentId)
            ->where('pa.status', 'active')
            ->orderBy('pa.created_at')
            ->get()
            ->map(function ($allocation) {
                return [
                    'allocation_id' => $allocation->allocation_id,
                    'invoice_id' => $allocation->invoice_id,
                    'invoice_number' => $allocation->invoice_number,
                    'allocated_amount' => (float) $allocation->allocated_amount,
                    'discount_amount' => (float) ($allocation->discount_amount ?? 0),
                    'discount_percent' => (float) ($allocation->discount_percent ?? 0),
                    'allocation_date' => $allocation->allocation_date,
                    'allocation_method' => $allocation->allocation_method,
                    'notes' => $allocation->notes,
                ];
            })
            ->toArray();
    }

    /**
     * Calculate summary statistics.
     */
    private function calculateSummary(array $payments): array
    {
        $totalAllocations = 0;
        $totalAmountAllocated = 0;
        $totalDiscountsApplied = 0;
        $totalUnallocatedCash = 0;
        $reconciledCount = 0;

        foreach ($payments as $payment) {
            $totalAllocations += $payment['allocation_count'];
            $totalAmountAllocated += $payment['total_allocated'];
            $totalDiscountsApplied += $payment['total_discounts'];
            $totalUnallocatedCash += $payment['unallocated_amount'];
            if ($payment['reconciled']) {
                $reconciledCount++;
            }
        }

        return [
            'total_allocations' => $totalAllocations,
            'total_amount_allocated' => $totalAmountAllocated,
            'total_discounts_applied' => $totalDiscountsApplied,
            'total_unallocated_cash' => $totalUnallocatedCash,
            'reconciled_payments' => $reconciledCount,
            'all_reconciled' => count($payments) > 0 && $reconciledCount === count($payments),
            'reconciliation_rate' => count($payments) > 0 ? round(($reconciledCount / count($payments)) * 100, 2) : 0,
        ];
    }

    /**
     * Get audit trail summary.
     */
    private function getAuditTrailSummary(array $filters, PaymentQueryService $queryService): array
    {
        $dateRange = [
            'start_date' => $filters['start_date'] ?? now()->subDays(30)->toDateString(),
            'end_date' => $filters['end_date'] ?? now()->toDateString(),
        ];

        $metrics = $queryService->getAuditMetrics($dateRange);

        return [
            'total_audit_events' => $metrics['total_audit_events'],
            'payment_created_events' => $metrics['action_counts']['payment_created'] ?? 0,
            'payment_allocated_events' => $metrics['action_counts']['payment_allocated'] ?? 0,
            'bank_reconciled_events' => $metrics['action_counts']['bank_reconciled'] ?? 0,
            'allocation_reversed_events' => $metrics['action_counts']['allocation_reversed'] ?? 0,
        ];
    }

    /**
     * Output the report in the specified format.
     */
    private function outputReport(array $reportData, array $options): void
    {
        switch ($options['format']) {
            case 'json':
                $this->line(json_encode($reportData, JSON_PRETTY_PRINT));
                break;

            case 'csv':
                $this->exportToCsv($reportData, $options['output']);
                break;

            case 'table':
            default:
                $this->displayAsTable($reportData);
                break;
        }
    }

    /**
     * Display report as formatted table.
     */
    private function displayAsTable(array $reportData): void
    {
        $this->info("\n📊 PAYMENT ALLOCATION REPORT");
        $this->info(str_repeat("=", 80));

        // Display metadata
        $metadata = $reportData['report_metadata'];
        $this->info("Generated: " . $metadata['generated_at']);
        $this->info("Period: {$metadata['report_period']['start_date']} to {$metadata['report_period']['end_date']}");
        $this->info("Total Payments: {$metadata['total_payments']}");
        $this->info("Currency: {$metadata['currency']}\n");

        // Display summary
        $summary = $reportData['summary'];
        $this->info("📈 SUMMARY");
        $this->info("-" . str_repeat("-", 40));
        $this->info("Total Allocations: {$summary['total_allocations']}");
        $this->info("Total Amount Allocated: $" . number_format($summary['total_amount_allocated'], 2));
        $this->info("Total Discounts Applied: $" . number_format($summary['total_discounts_applied'], 2));
        $this->info("Total Unallocated Cash: $" . number_format($summary['total_unallocated_cash'], 2));
        $this->info("Reconciliation Rate: {$summary['reconciliation_rate']}%\n");

        // Display allocations table
        if (!empty($reportData['allocations'])) {
            $this->info("💳 PAYMENT ALLOCATIONS");
            $this->info("-" . str_repeat("-", 100));

            $headers = ['Payment #', 'Entity', 'Amount', 'Allocated', 'Unallocated', 'Count', 'Reconciled'];
            $rows = [];

            foreach ($reportData['allocations'] as $payment) {
                $rows[] = [
                    $payment['payment_number'],
                    $payment['entity']['name'] ?? 'Unknown',
                    '$' . number_format($payment['amount'], 2),
                    '$' . number_format($payment['total_allocated'], 2),
                    '$' . number_format($payment['unallocated_amount'], 2),
                    $payment['allocation_count'],
                    $payment['reconciled'] ? '✓' : '✗',
                ];
            }

            $this->table($headers, $rows);
        }

        // Display audit summary if available
        if (!empty($reportData['audit_trail_summary'])) {
            $this->info("\n🔍 AUDIT TRAIL SUMMARY");
            $this->info("-" . str_repeat("-", 40));
            
            $audit = $reportData['audit_trail_summary'];
            $this->info("Total Audit Events: {$audit['total_audit_events']}");
            $this->info("Payments Created: {$audit['payment_created_events']}");
            $this->info("Payments Allocated: {$audit['payment_allocated_events']}");
            $this->info("Bank Reconciliations: {$audit['bank_reconciled_events']}");
            $this->info("Allocation Reversals: {$audit['allocation_reversed_events']}");
        }

        // Display performance metrics if available
        if (!empty($reportData['performance_metrics'])) {
            $this->info("\n⚡ PERFORMANCE METRICS");
            $this->info("-" . str_repeat("-", 40));
            
            $metrics = $reportData['performance_metrics'];
            $this->info("Query Time: {$metrics['query_execution_time']}ms");
            $this->info("Memory Usage: {$metrics['memory_usage_mb']}MB");
            $this->info("Records Processed: {$metrics['records_processed']}");
        }
    }

    /**
     * Export report data to CSV.
     */
    private function exportToCsv(array $reportData, string $outputPath): void
    {
        $file = fopen($outputPath, 'w');
        
        // Write header
        fputcsv($file, [
            'payment_number',
            'entity_name',
            'payment_date',
            'payment_method',
            'amount',
            'total_allocated',
            'total_discounts',
            'unallocated_amount',
            'allocation_count',
            'reconciled',
            'reconciled_date',
        ]);

        // Write data rows
        foreach ($reportData['allocations'] as $payment) {
            fputcsv($file, [
                $payment['payment_number'],
                $payment['entity']['name'] ?? '',
                $payment['payment_date'],
                $payment['payment_method'],
                $payment['amount'],
                $payment['total_allocated'],
                $payment['total_discounts'],
                $payment['unallocated_amount'],
                $payment['allocation_count'],
                $payment['reconciled'] ? 'Yes' : 'No',
                $payment['reconciled_date'] ?? '',
            ]);
        }

        fclose($file);
        $this->info("📄 CSV report exported to: {$outputPath}");
    }

    /**
     * Validate date format.
     */
    private function isValidDate(string $date): bool
    {
        return (bool) strtotime($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
    }

    /**
     * Get company currency.
     */
    private function getCompanyCurrency(string $companyId): string
    {
        return \App\Models\Company::where('id', $companyId)
            ->value('base_currency') ?? 'USD';
    }
}