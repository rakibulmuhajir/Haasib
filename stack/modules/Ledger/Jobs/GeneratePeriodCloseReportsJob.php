<?php

namespace Modules\Ledger\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Ledger\Domain\PeriodClose\Models\PeriodClose;

class GeneratePeriodCloseReportsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = [60, 300, 900]; // 1min, 5min, 15min

    public function __construct(
        private string $reportId,
        private string $periodCloseId,
        private array $reportTypes,
        private User $user
    ) {
        $this->onQueue('reports');
    }

    public function handle(): void
    {
        $startTime = now();

        try {
            $periodClose = PeriodClose::findOrFail($this->periodCloseId);
            $period = $periodClose->accountingPeriod;

            Log::info('Starting report generation', [
                'report_id' => $this->reportId,
                'period_close_id' => $this->periodCloseId,
                'report_types' => $this->reportTypes,
                'user_id' => $this->user->id,
            ]);

            $filePaths = [];
            $generationMetadata = [];

            foreach ($this->reportTypes as $reportType) {
                $reportStartTime = now();

                try {
                    $filePath = $this->generateSingleReport($reportType, $periodClose, $period);
                    $filePaths[$reportType] = $filePath;

                    $generationMetadata[$reportType] = [
                        'generated_at' => now()->toISOString(),
                        'generation_time_ms' => now()->diffInMilliseconds($reportStartTime),
                        'file_size_bytes' => Storage::disk('local')->size($filePath),
                        'status' => 'success',
                    ];
                } catch (\Exception $e) {
                    Log::error("Failed to generate report {$reportType}", [
                        'report_id' => $this->reportId,
                        'report_type' => $reportType,
                        'error' => $e->getMessage(),
                    ]);

                    $generationMetadata[$reportType] = [
                        'generated_at' => now()->toISOString(),
                        'generation_time_ms' => now()->diffInMilliseconds($reportStartTime),
                        'status' => 'failed',
                        'error' => $e->getMessage(),
                    ];

                    // Continue generating other reports even if one fails
                }
            }

            // Update the report record
            DB::table('period_close_reports')
                ->where('id', $this->reportId)
                ->update([
                    'status' => 'completed',
                    'generated_at' => now(),
                    'file_paths' => json_encode($filePaths),
                    'completed_by' => $this->user->id,
                    'error_message' => null,
                    'metadata' => DB::raw("jsonb_set(
                        metadata,
                        '{generation_results}',
                        '".json_encode($generationMetadata)."'::jsonb
                    )"),
                    'updated_at' => now(),
                ]);

            // Log successful completion
            $totalTime = now()->diffInMilliseconds($startTime);
            Log::info('Report generation completed', [
                'report_id' => $this->reportId,
                'total_time_ms' => $totalTime,
                'reports_generated' => count($filePaths),
                'reports_requested' => count($this->reportTypes),
            ]);

        } catch (\Exception $e) {
            Log::error('Report generation failed', [
                'report_id' => $this->reportId,
                'period_close_id' => $this->periodCloseId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update the report record with error
            DB::table('period_close_reports')
                ->where('id', $this->reportId)
                ->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'updated_at' => now(),
                ]);

            throw $e;
        }
    }

    /**
     * Generate a single report file.
     */
    private function generateSingleReport(string $reportType, PeriodClose $periodClose, $period): string
    {
        $fileName = $this->generateFileName($reportType, $period);
        $filePath = "reports/{$this->reportId}/{$fileName}";

        // Create directory if it doesn't exist
        $directory = dirname($filePath);
        Storage::disk('local')->makeDirectory($directory);

        // Generate the actual report content based on type
        $content = $this->generateReportContent($reportType, $periodClose, $period);

        // Store the file
        Storage::disk('local')->put($filePath, $content);

        return $filePath;
    }

    /**
     * Generate file name for a report.
     */
    private function generateFileName(string $reportType, $period): string
    {
        $periodName = $period->name ?? $period->start_date->format('Y-m');
        $reportTypeLabel = str_replace('_', ' ', $reportType);
        $reportTypeLabel = ucwords($reportTypeLabel);

        return "{$reportTypeLabel}_{$periodName}.pdf";
    }

    /**
     * Generate the actual report content.
     */
    private function generateReportContent(string $reportType, PeriodClose $periodClose, $period): string
    {
        // This is a placeholder implementation
        // In a real application, you would:
        // 1. Query the actual financial data
        // 2. Use a PDF generation library (like DomPDF or TCPDF)
        // 3. Generate proper financial statements

        $content = "Report: {$reportType}\n";
        $content .= "Period: {$period->name}\n";
        $content .= "Date Range: {$period->start_date} to {$period->end_date}\n";
        $content .= 'Generated: '.now()->toDateTimeString()."\n";
        $content .= "Generated By: {$this->user->name}\n";
        $content .= "\n";

        // Add specific content based on report type
        switch ($reportType) {
            case 'income_statement':
                $content .= $this->generateIncomeStatementContent($period);
                break;
            case 'balance_sheet':
                $content .= $this->generateBalanceSheetContent($period);
                break;
            case 'cash_flow':
                $content .= $this->generateCashFlowContent($period);
                break;
            case 'trial_balance':
                $content .= $this->generateTrialBalanceContent($period);
                break;
            default:
                $content .= "Report content for {$reportType} would be generated here.\n";
        }

        return $content;
    }

    /**
     * Generate income statement content.
     */
    private function generateIncomeStatementContent($period): string
    {
        return '
INCOME STATEMENT
================

Revenue:
  Sales Revenue..................... $500,000.00
  Service Revenue.................. $150,000.00
  Other Revenue..................... $25,000.00
Total Revenue...................... $675,000.00

Expenses:
  Cost of Goods Sold............... $300,000.00
  Salaries and Wages............... $125,000.00
  Rent Expense..................... $36,000.00
  Utilities....................... $12,000.00
  Depreciation..................... $18,000.00
  Other Expenses................... $15,000.00
Total Expenses.................... $506,000.00

Net Income......................... $169,000.00
';
    }

    /**
     * Generate balance sheet content.
     */
    private function generateBalanceSheetContent($period): string
    {
        return '
BALANCE SHEET
=============

ASSETS
Current Assets:
  Cash and Cash Equivalents........ $125,000.00
  Accounts Receivable.............. $85,000.00
  Inventory........................ $65,000.00
  Prepaid Expenses................ $8,000.00
Total Current Assets.............. $283,000.00

Fixed Assets:
  Equipment....................... $250,000.00
  Less: Accumulated Depreciation... ($75,000.00)
Net Fixed Assets.................. $175,000.00

Total Assets..................... $458,000.00

LIABILITIES AND EQUITY
Current Liabilities:
  Accounts Payable................. $35,000.00
  Accrued Expenses................ $12,000.00
  Current Portion of Debt......... $25,000.00
Total Current Liabilities........ $72,000.00

Long-term Debt................... $150,000.00
Total Liabilities................ $222,000.00

Equity:
  Common Stock.................... $100,000.00
  Retained Earnings............... $136,000.00
Total Equity..................... $236,000.00

Total Liabilities & Equity....... $458,000.00
';
    }

    /**
     * Generate cash flow content.
     */
    private function generateCashFlowContent($period): string
    {
        return '
CASH FLOW STATEMENT
===================

Cash Flows from Operating Activities:
  Net Income....................... $169,000.00
  Adjustments:
    Depreciation................... $18,000.00
    Increase in Accounts Receivable. ($15,000.00)
    Increase in Inventory.......... ($8,000.00)
    Increase in Prepaid Expenses.... ($2,000.00)
    Increase in Accounts Payable... $5,000.00
    Increase in Accrued Expenses.. $3,000.00
Net Cash from Operations......... $170,000.00

Cash Flows from Investing Activities:
  Purchase of Equipment........... ($50,000.00)
Net Cash from Investing......... ($50,000.00)

Cash Flows from Financing Activities:
  Debt Repayment.................. ($25,000.00)
Net Cash from Financing......... ($25,000.00)

Net Increase in Cash............. $95,000.00
Cash at Beginning of Period....... $30,000.00
Cash at End of Period............ $125,000.00
';
    }

    /**
     * Generate trial balance content.
     */
    private function generateTrialBalanceContent($period): string
    {
        return '
TRIAL BALANCE
==============

Account Title                    Debits        Credits
--------------------------------------------------------
Cash                            $125,000.00
Accounts Receivable               $85,000.00
Inventory                        $65,000.00
Prepaid Expenses                  $8,000.00
Equipment                       $250,000.00
Accumulated Depreciation                     $75,000.00
Accounts Payable                            $35,000.00
Accrued Expenses                           $12,000.00
Current Portion of Debt                     $25,000.00
Long-term Debt                            $150,000.00
Common Stock                             $100,000.00
Retained Earnings                        $136,000.00
Sales Revenue                                       $500,000.00
Service Revenue                                     $150,000.00
Other Revenue                                        $25,000.00
Cost of Goods Sold               $300,000.00
Salaries and Wages               $125,000.00
Rent Expense                      $36,000.00
Utilities                         $12,000.00
Depreciation                      $18,000.00
Other Expenses                    $15,000.00
--------------------------------------------------------
Totals                        $1,038,000.00   $1,038,000.00
';
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Report generation job failed permanently', [
            'report_id' => $this->reportId,
            'period_close_id' => $this->periodCloseId,
            'user_id' => $this->user->id,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        // Update the report record with permanent failure
        DB::table('period_close_reports')
            ->where('id', $this->reportId)
            ->update([
                'status' => 'failed',
                'error_message' => 'Report generation failed after '.$this->attempts().' attempts: '.$exception->getMessage(),
                'updated_at' => now(),
            ]);
    }
}
