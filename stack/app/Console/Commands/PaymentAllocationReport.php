<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\PaymentAllocationReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class PaymentAllocationReport extends Command
{
    protected $signature = 'payment:allocation:report 
                            {--start= : Start date for report period (YYYY-MM-DD, defaults to 30 days ago)}
                            {--end= : End date for report period (YYYY-MM-DD, defaults to today)}
                            {--type=comprehensive : Report type (comprehensive, daily_summary, customer_specific, analytics)}
                            {--customer= : Filter by customer ID (for customer-specific reports)}
                            {--format=table : Output format (table, json, csv)}
                            {--output= : Output file path (optional)}
                            {--email= : Email address to send report (optional)}
                            {--template= : Report template to use}
                            {--currency= : Currency code for report}
                            {--group-by=day : Group results by (day, week, month)}';

    protected $description = 'Generate payment allocation reports with various formats and options';

    public function handle(PaymentAllocationReportService $reportService): int
    {
        $this->info('📊 Payment Allocation Report Generator');
        $this->info('======================================');

        try {
            // Get company from context
            $company = $this->getCompanyFromContext();

            // Parse date range
            $dateRange = $this->parseDateRange();

            // Validate report options
            $this->validateReportOptions();

            // Generate report
            $this->info("Generating {$this->option('type')} report for period: {$dateRange['start']} to {$dateRange['end']}");

            $startTime = microtime(true);
            $report = $this->generateReport($reportService, $company, $dateRange);
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            // Display results
            $this->displayReport($report, $duration);

            // Handle output options
            $this->handleOutputOptions($report);

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->error('❌ Report Generation Error: '.$e->getMessage());
            $this->line('Stack trace:', 'error');
            $this->line($e->getTraceAsString(), 'error');

            return self::FAILURE;
        }
    }

    private function parseDateRange(): array
    {
        $startDate = $this->option('start');
        $endDate = $this->option('end');

        if (! $startDate) {
            $startDate = now()->subDays(30)->format('Y-m-d');
        }

        if (! $endDate) {
            $endDate = now()->format('Y-m-d');
        }

        // Validate dates
        $this->validateDate($startDate);
        $this->validateDate($endDate);

        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        if ($start->gt($end)) {
            throw new \InvalidArgumentException('Start date cannot be after end date');
        }

        return [
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
            'start_obj' => $start,
            'end_obj' => $end,
        ];
    }

    private function validateReportOptions(): void
    {
        $validTypes = ['comprehensive', 'daily_summary', 'customer_specific', 'analytics'];
        $type = $this->option('type');

        if (! in_array($type, $validTypes)) {
            throw new \InvalidArgumentException("Invalid report type: {$type}");
        }

        if ($type === 'customer_specific' && ! $this->option('customer')) {
            throw new \InvalidArgumentException('--customer option is required for customer-specific reports');
        }

        $validFormats = ['table', 'json', 'csv'];
        $format = $this->option('format');

        if (! in_array($format, $validFormats)) {
            throw new \InvalidArgumentException("Invalid output format: {$format}");
        }

        $validGroupBy = ['day', 'week', 'month'];
        $groupBy = $this->option('group-by');

        if (! in_array($groupBy, $validGroupBy)) {
            throw new \InvalidArgumentException("Invalid group-by option: {$groupBy}");
        }
    }

    private function generateReport(PaymentAllocationReportService $reportService, Company $company, array $dateRange)
    {
        $options = [
            'currency' => $this->option('currency') ?? 'USD',
            'group_by' => $this->option('group-by'),
            'include_charts' => true,
            'include_recommendations' => true,
            'template' => $this->option('template'),
        ];

        switch ($this->option('type')) {
            case 'comprehensive':
                return $reportService->generateAllocationReport(
                    $company,
                    $dateRange['start_obj'],
                    $dateRange['end_obj'],
                    $options
                );

            case 'daily_summary':
                // For daily summary, generate a report for each day in the range
                $dailyReports = [];
                $current = clone $dateRange['start_obj'];
                while ($current <= $dateRange['end_obj']) {
                    $dailyReports[] = $reportService->generateDailySummaryReport($company, $current);
                    $current->addDay();
                }
                return [
                    'report_type' => 'daily_summary',
                    'period' => [
                        'start' => $dateRange['start'],
                        'end' => $dateRange['end']
                    ],
                    'daily_reports' => $dailyReports,
                    'generated_at' => now()->toISOString(),
                ];

            case 'customer_specific':
                return $reportService->generateCustomerReport(
                    $company,
                    $this->option('customer'),
                    $dateRange['start_obj'],
                    $dateRange['end_obj'],
                    $options
                );

            case 'analytics':
                // Analytics not implemented yet, fall back to comprehensive
                return $reportService->generateAllocationReport(
                    $company,
                    $dateRange['start_obj'],
                    $dateRange['end_obj'],
                    array_merge($options, ['include_analytics' => true])
                );

            default:
                throw new \InvalidArgumentException("Unsupported report type: {$this->option('type')}");
        }
    }

    private function displayReport(array $report, float $duration): void
    {
        $this->info("\n✅ Report generated successfully!");
        $this->info("⏱️  Generated in: {$duration}ms");

        $format = $this->option('format');

        switch ($format) {
            case 'json':
                $this->displayJsonReport($report);
                break;

            case 'csv':
                $this->displayCsvReport($report);
                break;

            case 'table':
            default:
                $this->displayTableReport($report);
                break;
        }
    }

    private function displayTableReport(array $report): void
    {
        $this->info("\n📊 Report Summary:");
        
        // Handle different report structures
        if (isset($report['period'])) {
            // Custom structure (like daily_summary)
            $this->info("Period: {$report['period']['start']} to {$report['period']['end']}");
        } elseif (isset($report['report_metadata']['date_range'])) {
            // Standard service structure
            $dateRange = $report['report_metadata']['date_range'];
            $this->info("Period: {$dateRange['start_date']} to {$dateRange['end_date']}");
        }
        
        $generatedAt = $report['generated_at'] ?? $report['report_metadata']['generated_at'] ?? 'Unknown';
        $this->info("Generated: {$generatedAt}");
        
        $currency = $report['currency'] ?? 'USD';
        $this->info("Currency: {$currency}");

        if (isset($report['summary'])) {
            $this->displaySummary($report['summary']);
        } elseif (isset($report['summary_metrics'])) {
            $this->displaySummary($report['summary_metrics']);
        }

        if (isset($report['analytics'])) {
            $this->displayAnalytics($report['analytics']);
        } elseif (isset($report['efficiency_metrics'])) {
            $this->displayAnalytics($report['efficiency_metrics']);
        }

        if (isset($report['recommendations'])) {
            $this->displayRecommendations($report['recommendations']);
        }
    }

    private function displaySummary(array $summary): void
    {
        $this->info("\n📈 Summary Statistics:");

        $summaryData = [];
        foreach ($summary as $key => $value) {
            $summaryData[] = [
                'metric' => ucwords(str_replace('_', ' ', $key)),
                'value' => is_numeric($value) ? number_format($value, 2) : $value,
            ];
        }

        $headers = ['Metric', 'Value'];
        $rows = array_map(fn ($item) => [$item['metric'], $item['value']], $summaryData);
        $this->table($headers, $rows);
    }

    private function displayAnalytics(array $analytics): void
    {
        if (isset($analytics['allocation_efficiency'])) {
            $this->info("\n🎯 Allocation Efficiency:");
            $efficiency = $analytics['allocation_efficiency'];

            $this->line("  • Overall efficiency: {$efficiency['overall_efficiency']}%");
            $this->line("  • Average allocation time: {$efficiency['average_allocation_time']}ms");
            $this->line("  • Reversal rate: {$efficiency['reversal_rate']}%");
        }

        if (isset($analytics['strategy_performance'])) {
            $this->info("\n🧠 Strategy Performance:");

            $headers = ['Strategy', 'Usage', 'Success Rate', 'Avg Amount'];
            $rows = [];

            foreach ($analytics['strategy_performance'] as $strategy => $performance) {
                $rows[] = [
                    $strategy,
                    $performance['usage_count'],
                    $performance['success_rate'].'%',
                    number_format($performance['average_amount'], 2),
                ];
            }

            $this->table($headers, $rows);
        }
    }

    private function displayRecommendations(array $recommendations): void
    {
        $this->info("\n💡 Recommendations:");
        foreach ($recommendations as $recommendation) {
            $this->line("  • {$recommendation}");
        }
    }

    private function displayJsonReport(array $report): void
    {
        $this->line(json_encode($report, JSON_PRETTY_PRINT));
    }

    private function displayCsvReport(array $report): void
    {
        // For CSV, we'll export the main data sections
        $sections = ['summary', 'daily_data', 'customer_breakdown', 'strategy_usage'];

        foreach ($sections as $section) {
            if (isset($report[$section]) && is_array($report[$section])) {
                $this->info("\n=== {$section} ===");

                if (! empty($report[$section])) {
                    $headers = array_keys($report[$section][0]);
                    $this->line(implode(',', $headers));

                    foreach ($report[$section] as $row) {
                        $csvRow = array_map(function ($value) {
                            if (is_string($value) && (strpos($value, ',') !== false || strpos($value, '"') !== false)) {
                                return '"'.str_replace('"', '""', $value).'"';
                            }

                            return $value;
                        }, $row);

                        $this->line(implode(',', $csvRow));
                    }
                } else {
                    $this->line("No data available for {$section}");
                }
            }
        }
    }

    private function handleOutputOptions(array $report): void
    {
        // Save to file
        if ($outputPath = $this->option('output')) {
            $this->saveReportToFile($report, $outputPath);
        }

        // Send via email
        if ($email = $this->option('email')) {
            $this->sendReportByEmail($report, $email);
        }
    }

    private function saveReportToFile(array $report, string $outputPath): void
    {
        $content = match ($this->option('format')) {
            'json' => json_encode($report, JSON_PRETTY_PRINT),
            'csv' => $this->convertToCsv($report),
            default => $this->convertToText($report)
        };

        file_put_contents($outputPath, $content);
        $this->info("📁 Report saved to: {$outputPath}");
    }

    private function sendReportByEmail(array $report, string $email): void
    {
        // This would integrate with your email service
        $this->info('📧 Report sending functionality would be implemented here');
        $this->info("   Email: {$email}");
        $this->info("   Report type: {$this->option('type')}");
        $this->info("   Format: {$this->option('format')}");
    }

    private function convertToCsv(array $report): string
    {
        $csv = '';
        $sections = ['summary', 'daily_data', 'customer_breakdown'];

        foreach ($sections as $section) {
            if (isset($report[$section]) && ! empty($report[$section])) {
                $csv .= "{$section}\n";
                $headers = array_keys($report[$section][0]);
                $csv .= implode(',', $headers)."\n";

                foreach ($report[$section] as $row) {
                    $csv .= implode(',', $row)."\n";
                }

                $csv .= "\n";
            }
        }

        return $csv;
    }

    private function convertToText(array $report): string
    {
        $text = "Payment Allocation Report\n";
        $text .= "========================\n\n";
        $text .= "Period: {$report['period']['start']} to {$report['period']['end']}\n";
        $text .= "Generated: {$report['generated_at']}\n\n";

        if (isset($report['summary'])) {
            $text .= "Summary:\n";
            foreach ($report['summary'] as $key => $value) {
                $text .= '  '.ucwords(str_replace('_', ' ', $key)).": {$value}\n";
            }
        }

        return $text;
    }

    private function validateDate(string $date): void
    {
        if (! strtotime($date)) {
            throw new \InvalidArgumentException("Invalid date format: {$date}. Use YYYY-MM-DD format.");
        }
    }

    private function getCompanyFromContext(): Company
    {
        // This would be implemented based on your context system
        $company = Company::first();
        if (! $company) {
            throw new \RuntimeException('No company found. Please specify --company=<id>');
        }

        return $company;
    }
}
