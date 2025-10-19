<?php

namespace Modules\Reporting\Actions\Reports;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Reporting\Services\FinancialStatementService;
use Modules\Reporting\Services\TrialBalanceService;
use Modules\Reporting\Jobs\GenerateReportJob;

class GenerateReportAction
{
    public function __construct(
        private FinancialStatementService $financialStatementService,
        private TrialBalanceService $trialBalanceService
    ) {}

    /**
     * Execute the report generation action
     */
    public function execute(string $companyId, array $parameters, bool $async = true): array
    {
        $this->validateParameters($parameters);
        $this->validatePermissions($companyId);

        $reportId = $this->createReportRecord($companyId, $parameters);

        try {
            if ($async) {
                // Queue the report generation job
                $job = new GenerateReportJob($reportId, $companyId, $parameters);
                
                dispatch($job)->onQueue('reports');
                
                Log::info('Report generation job queued', [
                    'company_id' => $companyId,
                    'report_id' => $reportId,
                    'report_type' => $parameters['report_type'],
                ]);

                return [
                    'report_id' => $reportId,
                    'status' => 'queued',
                    'estimated_completion_seconds' => $this->getEstimatedCompletionTime($parameters['report_type']),
                    'message' => 'Report generation has been queued and will begin shortly.',
                ];
            } else {
                // Generate report synchronously (for testing or immediate results)
                $this->performReportGeneration($reportId, $companyId, $parameters);
                
                return [
                    'report_id' => $reportId,
                    'status' => 'completed',
                    'message' => 'Report generated successfully.',
                    'download_url' => route('reporting.reports.download', $reportId),
                ];
            }
        } catch (\Exception $e) {
            // Update report status to failed
            $this->updateReportStatus($reportId, 'failed', $e->getMessage());
            
            Log::error('Report generation failed', [
                'company_id' => $companyId,
                'report_id' => $reportId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Perform the actual report generation
     */
    public function performReportGeneration(string $reportId, string $companyId, array $parameters): void
    {
        try {
            // Update report status to running
            $this->updateReportStatus($reportId, 'running');

            $reportData = match ($parameters['report_type']) {
                'income_statement' => $this->financialStatementService->generateIncomeStatement($companyId, $parameters),
                'balance_sheet' => $this->financialStatementService->generateBalanceSheet($companyId, $parameters),
                'cash_flow' => $this->financialStatementService->generateCashFlowStatement($companyId, $parameters),
                'trial_balance' => $this->trialBalanceService->generateTrialBalance($companyId, $parameters),
                default => throw new \InvalidArgumentException("Unsupported report type: {$parameters['report_type']}")
            };

            // Add metadata to report data
            $reportData['report_id'] = $reportId;
            $reportData['company_id'] = $companyId;
            $reportData['generated_by'] = auth()->id();
            $reportData['file_path'] = $this->generateReportFile($reportId, $parameters['report_type'], $reportData);
            $reportData['file_size'] = Storage::size($reportData['file_path']);

            // Store report data in database
            $this->storeReportData($reportId, $reportData);

            // Update report status to generated
            $this->updateReportStatus($reportId, 'generated', null, [
                'file_path' => $reportData['file_path'],
                'file_size' => $reportData['file_size'],
                'generated_at' => now(),
            ]);

            Log::info('Report generated successfully', [
                'company_id' => $companyId,
                'report_id' => $reportId,
                'report_type' => $parameters['report_type'],
                'file_size' => $reportData['file_size'],
            ]);

        } catch (\Exception $e) {
            $this->updateReportStatus($reportId, 'failed', $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create report record in database
     */
    private function createReportRecord(string $companyId, array $parameters): string
    {
        $reportId = DB::table('rpt.reports')->insertGetId([
            'company_id' => $companyId,
            'report_type' => $parameters['report_type'],
            'name' => $this->generateReportName($parameters),
            'parameters' => json_encode($parameters),
            'status' => 'queued',
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (string) $reportId;
    }

    /**
     * Update report status
     */
    private function updateReportStatus(string $reportId, string $status, ?string $error = null, array $additionalData = []): void
    {
        $updateData = [
            'status' => $status,
            'updated_at' => now(),
        ];

        if ($status === 'generated') {
            $updateData['generated_at'] = now();
        } elseif ($status === 'failed') {
            $updateData['failure_reason'] = $error;
        }

        $updateData = array_merge($updateData, $additionalData);

        DB::table('rpt.reports')
            ->where('report_id', $reportId)
            ->update($updateData);
    }

    /**
     * Store report data
     */
    private function storeReportData(string $reportId, array $reportData): void
    {
        DB::table('rpt.reports')
            ->where('report_id', $reportId)
            ->update([
                'payload' => json_encode($reportData),
                'updated_at' => now(),
            ]);
    }

    /**
     * Generate report file
     */
    private function generateReportFile(string $reportId, string $reportType, array $reportData): string
    {
        $format = $this->getReportFormat($reportData);
        $filename = "{$reportType}_{$reportId}.{$format}";
        $filePath = "reports/{$reportId}/{$filename}";

        switch ($format) {
            case 'json':
                $content = json_encode($reportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                Storage::put($filePath, $content);
                break;
                
            case 'pdf':
                $content = $this->generatePdfReport($reportData);
                Storage::put($filePath, $content);
                break;
                
            case 'csv':
                $content = $this->generateCsvReport($reportData);
                Storage::put($filePath, $content);
                break;
                
            default:
                throw new \InvalidArgumentException("Unsupported export format: {$format}");
        }

        return $filePath;
    }

    /**
     * Generate PDF report
     */
    private function generatePdfReport(array $reportData): string
    {
        // This would integrate with a PDF library like DomPDF or MPDF
        // For now, return a placeholder
        $pdfContent = $this->generateHtmlReport($reportData);
        
        // In a real implementation, you would convert HTML to PDF
        return $pdfContent;
    }

    /**
     * Generate HTML report
     */
    private function generateHtmlReport(array $reportData): string
    {
        $statementType = $reportData['statement_type'] ?? 'report';
        $title = ucwords(str_replace('_', ' ', $statementType));
        
        $html = "<!DOCTYPE html>
<html>
<head>
    <title>{$title}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .section { margin: 30px 0; }
        .total { font-weight: bold; border-top: 2px solid #333; }
    </style>
</head>
<body>
    <h1>{$title}</h1>
    <p>Generated: {$reportData['generated_at']}</p>
    <p>Company: {$reportData['company_id']}</p>";
        
        // Add report-specific content based on type
        if (isset($reportData['sections'])) {
            foreach ($reportData['sections'] as $sectionTitle => $section) {
                if (is_array($section) && isset($section['title'])) {
                    $html .= "<div class='section'><h2>{$section['title']}</h2>";
                    
                    if (isset($section['lines'])) {
                        $html .= "<table>
                            <thead><tr><th>Description</th><th>Current</th><th>Comparison</th><th>Variance</th></tr></thead>
                            <tbody>";
                        
                        foreach ($section['lines'] as $line) {
                            $current = isset($line['current_amount']) ? number_format($line['current_amount'], 2) : '-';
                            $comparison = isset($line['comparison_amount']) ? number_format($line['comparison_amount'], 2) : '-';
                            $variance = isset($line['variance_amount']) ? number_format($line['variance_amount'], 2) : '-';
                            
                            $html .= "<tr>
                                <td>{$line['account_category'] ?? ''}</td>
                                <td>{$current}</td>
                                <td>{$comparison}</td>
                                <td>{$variance}</td>
                            </tr>";
                        }
                        
                        $html .= "</tbody></table>";
                    }
                    
                    $html .= "</div>";
                }
            }
        }
        
        $html .= "</body></html>";
        
        return $html;
    }

    /**
     * Generate CSV report
     */
    private function generateCsvReport(array $reportData): string
    {
        $csv = '';
        
        // Add header
        $csv .= "Report Type,Account Category,Current Amount,Comparison Amount,Variance\n";
        
        // Add data rows
        if (isset($reportData['sections'])) {
            foreach ($reportData['sections'] as $section) {
                if (is_array($section) && isset($section['title'])) {
                    if (isset($section['lines'])) {
                        foreach ($section['lines'] as $line) {
                            $csv .= "{$section['title']},{$line['account_category']},";
                            $csv .= ($line['current_amount'] ?? 0) . ",";
                            $csv .= ($line['comparison_amount'] ?? 0) . ",";
                            $csv .= ($line['variance_amount'] ?? 0) . "\n";
                        }
                    }
                }
            }
        }
        
        return $csv;
    }

    /**
     * Get report format
     */
    private function getReportFormat(array $reportData): string
    {
        return $reportData['export_format'] ?? 'json';
    }

    /**
     * Generate report name
     */
    private function generateReportName(array $parameters): string
    {
        $type = $parameters['report_type'];
        $dateRange = $parameters['date_range'] ?? null;
        
        if ($dateRange) {
            $startDate = Carbon::parse($dateRange['start'])->format('M j Y');
            $endDate = Carbon::parse($dateRange['end'])->format('M j Y');
            return ucfirst(str_replace('_', ' ', $type)) . " - {$startDate} to {$endDate}";
        }
        
        return ucfirst(str_replace('_', ' ', $type));
    }

    /**
     * Get estimated completion time in seconds
     */
    private function getEstimatedCompletionTime(string $reportType): int
    {
        return match ($reportType) {
            'trial_balance' => 10,
            'income_statement' => 15,
            'balance_sheet' => 20,
            'cash_flow' => 25,
            default => 30,
        };
    }

    /**
     * Validate report parameters
     */
    private function validateParameters(array $parameters): void
    {
        $requiredFields = ['report_type'];
        
        foreach ($requiredFields as $field) {
            if (!isset($parameters[$field]) || empty($parameters[$field])) {
                throw new \InvalidArgumentException("Missing required parameter: {$field}");
            }
        }

        $validTypes = ['income_statement', 'balance_sheet', 'cash_flow', 'trial_balance'];
        if (!in_array($parameters['report_type'], $validTypes)) {
            throw new \InvalidArgumentException("Invalid report type. Valid types: " . implode(', ', $validTypes));
        }

        // Validate date range if provided
        if (isset($parameters['date_range'])) {
            $dateRange = $parameters['date_range'];
            if (!isset($dateRange['start']) || !isset($dateRange['end'])) {
                throw new \InvalidArgumentException("Date range must include both start and end dates");
            }
            
            $start = Carbon::parse($dateRange['start']);
            $end = Carbon::parse($dateRange['end']);
            
            if ($start->greaterThan($end)) {
                throw new \InvalidArgumentException("Start date must be before or equal to end date");
            }
        }
    }

    /**
     * Validate user permissions
     */
    private function validatePermissions(string $companyId): void
    {
        $user = auth()->user();
        
        if (!$user) {
            throw new \UnauthorizedException('User not authenticated');
        }

        // Check if user has access to the company
        $hasAccess = DB::table('auth.company_user')
            ->where('company_id', $companyId)
            ->where('user_id', $user->id)
            ->exists();

        if (!$hasAccess) {
            throw new \UnauthorizedException('User does not have access to this company');
        }

        // Check for specific reporting permission
        if (!$user->can('reporting.reports.generate')) {
            throw new \UnauthorizedException('User does not have permission to generate reports');
        }
    }

    /**
     * Get report status
     */
    public function getReportStatus(string $reportId): array
    {
        $report = DB::table('rpt.reports')
            ->where('report_id', $reportId)
            ->first();

        if (!$report) {
            throw new \InvalidArgumentException("Report not found: {$reportId}");
        }

        return [
            'report_id' => $report->report_id,
            'company_id' => $report->company_id,
            'report_type' => $report->report_type,
            'name' => $report->name,
            'status' => $report->status,
            'created_at' => $report->created_at,
            'generated_at' => $report->generated_at,
            'expires_at' => $report->expires_at,
            'failure_reason' => $report->failure_reason,
            'file_size' => $report->file_size,
        ];
    }

    /**
     * Get report file
     */
    public function getReportFile(string $reportId): array
    {
        $report = DB::table('rpt.reports')
            ->where('report_id', $reportId)
            ->where('status', 'generated')
            ->first();

        if (!$report) {
            throw new \InvalidArgumentException("Generated report not found: {$reportId}");
        }

        if (!Storage::exists($report->file_path)) {
            throw new \RuntimeException("Report file not found: {$report->file_path}");
        }

        return [
            'file_path' => $report->file_path,
            'file_name' => basename($report->file_path),
            'file_size' => $report->file_size,
            'mime_type' => $report->mime_type,
            'download_url' => route('reporting.reports.download', $reportId),
        ];
    }

    /**
     * Delete report
     */
    public function deleteReport(string $reportId): void
    {
        $report = DB::table('rpt.reports')
            ->where('report_id', $reportId)
            ->first();

        if (!$report) {
            throw new \InvalidArgumentException("Report not found: {$reportId}");
        }

        // Delete file if exists
        if ($report->file_path && Storage::exists($report->file_path)) {
            Storage::delete($report->file_path);
        }

        // Delete database record
        DB::table('rpt.reports')
            ->where('report_id', $reportId)
            ->delete();

        Log::info('Report deleted', ['report_id' => $reportId]);
    }

    /**
     * Get list of reports for a company
     */
    public function getReports(string $companyId, array $filters = []): array
    {
        $query = DB::table('rpt.reports')
            ->where('company_id', $companyId)
            ->orderBy('created_at', 'desc');

        // Apply filters
        if (isset($filters['report_type'])) {
            $query->where('report_type', $filters['report_type']);
        }
        
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        
        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        $reports = $query->get();

        return $reports->map(function ($report) {
            return [
                'report_id' => $report->report_id,
                'report_type' => $report->report_type,
                'name' => $report->name,
                'status' => $report->status,
                'created_at' => $report->created_at,
                'generated_at' => $report->generated_at,
                'expires_at' => $report->expires_at,
                'file_size' => $report->file_size,
            ];
        })->toArray();
    }
}