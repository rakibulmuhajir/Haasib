<?php

namespace Modules\Ledger\Services;

use App\Models\BankReconciliation;
use App\Models\BankReconciliationMatch;
use App\Models\BankReconciliationAdjustment;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class BankReconciliationReportService
{
    private string $reportsDisk = 'local';
    private string $reportsPath = 'bank-reconciliation-reports';

    public function __construct()
    {
        // Ensure reports directory exists
        Storage::disk($this->reportsDisk)->makeDirectory($this->reportsPath);
    }

    /**
     * Generate reconciliation report in specified format.
     */
    public function generateReport(BankReconciliation $reconciliation, string $format = 'json', ?User $user = null): string|array
    {
        $user = $user ?: Auth::user();
        
        $this->validateReportAccess($reconciliation, $user);
        
        $reportData = $this->prepareReportData($reconciliation);
        
        switch (strtolower($format)) {
            case 'pdf':
                return $this->generatePdfReport($reconciliation, $reportData);
            case 'json':
                return $reportData;
            case 'csv':
                return $this->generateCsvReport($reconciliation, $reportData);
            default:
                throw new InvalidArgumentException("Unsupported report format: {$format}");
        }
    }

    /**
     * Generate variance analysis report.
     */
    public function generateVarianceAnalysis(BankReconciliation $reconciliation): array
    {
        $user = Auth::user();
        $this->validateReportAccess($reconciliation, $user);

        $variance = $reconciliation->getSummaryStats()['variance'];
        $unmatchedItems = $this->getUnmatchedItems($reconciliation);
        $adjustments = $this->getAdjustmentsSummary($reconciliation);

        return [
            'reconciliation_id' => $reconciliation->id,
            'statement_period' => $reconciliation->statement->statement_period,
            'bank_account' => $reconciliation->ledgerAccount->name,
            'variance_amount' => $variance['amount'],
            'variance_formatted' => $variance['formatted'],
            'variance_percentage' => $variance['percentage'],
            'variance_status' => $variance['status'],
            'is_balanced' => $variance['is_balanced'],
            'unmatched_items' => $unmatchedItems,
            'adjustments' => $adjustments,
            'recommendations' => $this->generateVarianceRecommendations($variance, $unmatchedItems, $adjustments),
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Generate audit trail report.
     */
    public function generateAuditTrail(BankReconciliation $reconciliation): array
    {
        $user = Auth::user();
        $this->validateReportAccess($reconciliation, $user);

        $activities = $reconciliation->activities()
            ->with('causer')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'description' => $activity->description,
                    'causer' => $activity->causer?->name,
                    'properties' => $activity->properties,
                    'created_at' => $activity->created_at->toISOString(),
                    'event_type' => $this->extractEventType($activity),
                ];
            });

        $statusChanges = $this->extractStatusChanges($activities);
        $accessLog = $this->extractAccessLog($activities);

        return [
            'reconciliation_id' => $reconciliation->id,
            'statement_period' => $reconciliation->statement->statement_period,
            'bank_account' => $reconciliation->ledgerAccount->name,
            'activities' => $activities->toArray(),
            'status_changes' => $statusChanges,
            'access_log' => $accessLog,
            'summary' => [
                'total_activities' => $activities->count(),
                'unique_users' => $activities->pluck('causer')->unique()->filter()->count(),
                'date_range' => [
                    'first_activity' => $activities->last()?['created_at'],
                    'last_activity' => $activities->first()?['created_at'],
                ],
            ],
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Generate comprehensive reconciliation summary.
     */
    public function generateSummaryReport(BankReconciliation $reconciliation): array
    {
        $user = Auth::user();
        $this->validateReportAccess($reconciliation, $user);

        $summaryStats = $reconciliation->getSummaryStats();
        $breakdown = $reconciliation->getBreakdown();

        return [
            'reconciliation' => [
                'id' => $reconciliation->id,
                'status' => $reconciliation->status,
                'started_at' => $reconciliation->started_at?->toISOString(),
                'completed_at' => $reconciliation->completed_at?->toISOString(),
                'locked_at' => $reconciliation->locked_at?->toISOString(),
                'active_duration' => $reconciliation->active_duration,
            ],
            'statement' => [
                'id' => $reconciliation->statement->id,
                'period' => $reconciliation->statement->statement_period,
                'opening_balance' => $reconciliation->statement->formatted_opening_balance,
                'closing_balance' => $reconciliation->statement->formatted_closing_balance,
                'period_amount' => number_format($reconciliation->statement->closing_balance - $reconciliation->statement->opening_balance, 2),
                'currency' => $reconciliation->statement->currency,
                'lines_count' => $reconciliation->statement->total_lines,
            ],
            'bank_account' => [
                'id' => $reconciliation->ledgerAccount->id,
                'name' => $reconciliation->ledgerAccount->name,
                'account_number' => $reconciliation->ledgerAccount->account_number,
            ],
            'performance' => [
                'matches' => $summaryStats['matches'],
                'adjustments' => $summaryStats['adjustments'],
                'variance' => $summaryStats['variance'],
                'progress' => $summaryStats['completeness'],
            ],
            'breakdown' => $breakdown,
            'quality_metrics' => [
                'auto_match_rate' => $this->calculateAutoMatchRate($reconciliation),
                'average_confidence_score' => $this->calculateAverageConfidence($reconciliation),
                'reconciliation_efficiency' => $this->calculateEfficiencyScore($reconciliation),
            ],
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Prepare comprehensive report data.
     */
    private function prepareReportData(BankReconciliation $reconciliation): array
    {
        $reconciliation->load([
            'statement.bankStatementLines',
            'matches.statementLine',
            'matches.source',
            'adjustments',
            'ledgerAccount',
            'startedBy',
            'completedBy',
        ]);

        return [
            'reconciliation' => [
                'id' => $reconciliation->id,
                'status' => $reconciliation->status,
                'variance' => $reconciliation->formatted_variance,
                'variance_status' => $reconciliation->variance_status,
                'started_at' => $reconciliation->started_at?->toISOString(),
                'completed_at' => $reconciliation->completed_at?->toISOString(),
                'locked_at' => $reconciliation->locked_at?->toISOString(),
                'active_duration' => $reconciliation->active_duration,
                'notes' => $reconciliation->notes,
                'percent_complete' => $reconciliation->percent_complete,
            ],
            'statement' => [
                'id' => $reconciliation->statement->id,
                'name' => $reconciliation->statement->statement_name,
                'period' => $reconciliation->statement->statement_period,
                'opening_balance' => $reconciliation->statement->formatted_opening_balance,
                'closing_balance' => $reconciliation->statement->formatted_closing_balance,
                'currency' => $reconciliation->statement->currency,
                'lines_count' => $reconciliation->statement->total_lines,
                'statement_date' => $reconciliation->statement->statement_date->toDateString(),
            ],
            'bank_account' => [
                'id' => $reconciliation->ledgerAccount->id,
                'name' => $reconciliation->ledgerAccount->name,
                'account_number' => $reconciliation->ledgerAccount->account_number,
            ],
            'matches' => $reconciliation->matches->map(function ($match) {
                return [
                    'id' => $match->id,
                    'statement_line_id' => $match->statement_line_id,
                    'source_type' => $match->source_type,
                    'source_id' => $match->source_id,
                    'source_display_name' => $match->source_display_name,
                    'amount' => $match->formatted_amount,
                    'auto_matched' => $match->auto_matched,
                    'confidence_score' => $match->formatted_confidence_score,
                    'confidence_level' => $match->confidence_level,
                    'matched_at' => $match->matched_at->toISOString(),
                    'matched_by' => $match->matchedBy?->name,
                    'statement_line_description' => $match->statementLine?->description,
                    'statement_line_date' => $match->statementLine?->transaction_date?->toDateString(),
                ];
            })->toArray(),
            'adjustments' => $reconciliation->adjustments->map(function ($adjustment) {
                return [
                    'id' => $adjustment->id,
                    'adjustment_type' => $adjustment->adjustment_type,
                    'type_display_name' => $adjustment->type_display_name,
                    'type_icon' => $adjustment->type_icon,
                    'type_color' => $adjustment->type_color,
                    'amount' => $adjustment->signed_amount,
                    'description' => $adjustment->description,
                    'created_at' => $adjustment->created_at->toISOString(),
                    'created_by' => $adjustment->createdBy?->name,
                    'journal_entry_id' => $adjustment->journal_entry_id,
                ];
            })->toArray(),
            'summary' => [
                'total_matches' => $reconciliation->matches()->count(),
                'auto_matches' => $reconciliation->matches()->where('auto_matched', true)->count(),
                'manual_matches' => $reconciliation->matches()->where('auto_matched', false)->count(),
                'total_adjustments' => $reconciliation->adjustments()->count(),
                'total_matched_amount' => $reconciliation->matches()->sum('amount'),
                'total_adjustments_amount' => $reconciliation->adjustments()->sum('amount'),
                'final_variance' => $reconciliation->formatted_variance,
                'variance_status' => $reconciliation->variance_status,
            ],
            'variance_analysis' => $this->generateVarianceAnalysis($reconciliation),
            'generated_at' => now()->toISOString(),
            'generated_by' => Auth::user()->name,
        ];
    }

    /**
     * Generate PDF report.
     */
    private function generatePdfReport(BankReconciliation $reconciliation, array $reportData): string
    {
        $filename = "reconciliation-{$reconciliation->id}-" . now()->format('Y-m-d-His') . ".pdf";
        $filepath = "{$this->reportsPath}/{$filename}";

        // Generate PDF content (simplified version - in production, use a proper PDF library)
        $content = $this->generatePdfContent($reportData);
        Storage::disk($this->reportsDisk)->put($filepath, $content);

        // Log report generation for audit
        $this->logReportGeneration($reconciliation, 'pdf', $filename);

        return $filepath;
    }

    /**
     * Generate CSV report.
     */
    private function generateCsvReport(BankReconciliation $reconciliation, array $reportData): string
    {
        $filename = "reconciliation-{$reconciliation->id}-" . now()->format('Y-m-d-His') . ".csv";
        $filepath = "{$this->reportsPath}/{$filename}";

        $content = $this->generateCsvContent($reportData);
        Storage::disk($this->reportsDisk)->put($filepath, $content);

        $this->logReportGeneration($reconciliation, 'csv', $filename);

        return $filepath;
    }

    /**
     * Generate PDF content (simplified version).
     */
    private function generatePdfContent(array $reportData): string
    {
        // This is a simplified HTML-to-PDF approach
        // In production, use a proper PDF library like DomPDF or TCPDF
        $html = view('reports.bank-reconciliation', $reportData)->render();
        return $html;
    }

    /**
     * Generate CSV content.
     */
    private function generateCsvContent(array $reportData): string
    {
        $csv = [];
        
        // Header
        $csv[] = 'Bank Reconciliation Report';
        $csv[] = "Generated: {$reportData['generated_at']}";
        $csv[] = "Generated By: {$reportData['generated_by']}";
        $csv[] = '';

        // Reconciliation Details
        $csv[] = 'Reconciliation Details';
        $csv[] = 'ID,Status,Period,Bank Account,Variance';
        $csv[] = sprintf(
            '%s,%s,%s,%s,%s',
            $reportData['reconciliation']['id'],
            $reportData['reconciliation']['status'],
            $reportData['statement']['period'],
            $reportData['bank_account']['name'],
            $reportData['reconciliation']['variance']
        );
        $csv[] = '';

        // Matches
        $csv[] = 'Matches';
        $csv[] = 'ID,Source Type,Amount,Auto-Matched,Date';
        foreach ($reportData['matches'] as $match) {
            $csv[] = sprintf(
                '%s,%s,%s,%s,%s',
                $match['id'],
                $match['source_display_name'],
                $match['amount'],
                $match['auto_matched'] ? 'Yes' : 'No',
                $match['matched_at']
            );
        }
        $csv[] = '';

        // Adjustments
        $csv[] = 'Adjustments';
        $csv[] = 'ID,Type,Amount,Description,Date';
        foreach ($reportData['adjustments'] as $adjustment) {
            $csv[] = sprintf(
                '%s,%s,%s,"%s",%s',
                $adjustment['id'],
                $adjustment['type_display_name'],
                $adjustment['amount'],
                str_replace('"', '""', $adjustment['description']),
                $adjustment['created_at']
            );
        }

        return implode("\n", $csv);
    }

    /**
     * Validate user has permission to access reports.
     */
    private function validateReportAccess(BankReconciliation $reconciliation, ?User $user): void
    {
        if (!$user) {
            throw new InvalidArgumentException('User must be authenticated to access reports');
        }

        if (!$user->hasPermissionTo('bank_reconciliation_reports.view')) {
            throw new InvalidArgumentException('User does not have permission to view reconciliation reports');
        }

        if ($reconciliation->company_id !== $user->current_company_id) {
            throw new InvalidArgumentException('Reconciliation does not belong to user\'s current company');
        }
    }

    /**
     * Get unmatched items for variance analysis.
     */
    private function getUnmatchedItems(BankReconciliation $reconciliation): array
    {
        $matchedStatementLineIds = $reconciliation->matches()->pluck('statement_line_id');
        
        $unmatchedLines = $reconciliation->statement->bankStatementLines()
            ->whereNotIn('id', $matchedStatementLineIds)
            ->get()
            ->map(function ($line) {
                return [
                    'id' => $line->id,
                    'date' => $line->transaction_date->toDateString(),
                    'description' => $line->description,
                    'amount' => $line->formatted_amount,
                    'reference' => $line->reference_number,
                ];
            });

        return $unmatchedLines->toArray();
    }

    /**
     * Get adjustments summary.
     */
    private function getAdjustmentsSummary(BankReconciliation $reconciliation): array
    {
        return $reconciliation->adjustments()
            ->selectRaw('adjustment_type, COUNT(*) as count, SUM(amount) as total_amount')
            ->groupBy('adjustment_type')
            ->get()
            ->map(function ($adjustment) {
                return [
                    'type' => $adjustment->adjustment_type,
                    'display_name' => $this->getAdjustmentTypeDisplayName($adjustment->adjustment_type),
                    'count' => $adjustment->count,
                    'total_amount' => number_format($adjustment->total_amount, 2),
                ];
            })
            ->toArray();
    }

    /**
     * Generate variance resolution recommendations.
     */
    private function generateVarianceRecommendations(array $variance, array $unmatchedItems, array $adjustments): array
    {
        $recommendations = [];

        if (!$variance['is_balanced']) {
            $recommendations[] = [
                'type' => 'variance',
                'priority' => 'high',
                'title' => 'Resolve Variance',
                'description' => "Current variance of {$variance['formatted']} must be resolved.",
                'action' => 'Create adjustments or match remaining transactions',
            ];
        }

        if (!empty($unmatchedItems)) {
            $recommendations[] = [
                'type' => 'matching',
                'priority' => 'medium',
                'title' => 'Complete Matching',
                'description' => count($unmatchedItems) . ' statement lines remain unmatched.',
                'action' => 'Run auto-match or create manual matches',
            ];
        }

        if (empty($recommendations)) {
            $recommendations[] = [
                'type' => 'completion',
                'priority' => 'low',
                'title' => 'Ready to Complete',
                'description' => 'Reconciliation is balanced and ready for completion.',
                'action' => 'Complete and lock the reconciliation',
            ];
        }

        return $recommendations;
    }

    /**
     * Extract event type from activity.
     */
    private function extractEventType($activity): string
    {
        return $activity->properties['action'] ?? 'unknown';
    }

    /**
     * Extract status changes from activities.
     */
    private function extractStatusChanges($activities): array
    {
        return $activities
            ->filter(fn($activity) => $this->extractEventType($activity) === 'status_change')
            ->map(function ($activity) {
                return [
                    'timestamp' => $activity['created_at'],
                    'old_status' => $activity['properties']['old_status'] ?? null,
                    'new_status' => $activity['properties']['new_status'] ?? null,
                    'user' => $activity['causer'],
                ];
            })
            ->toArray();
    }

    /**
     * Extract access log from activities.
     */
    private function extractAccessLog($activities): array
    {
        return $activities
            ->filter(fn($activity) => in_array($this->extractEventType($activity), ['report_accessed', 'view', 'export_operation']))
            ->map(function ($activity) {
                return [
                    'timestamp' => $activity['created_at'],
                    'action' => $activity['properties']['action'] ?? 'unknown',
                    'user' => $activity['causer'],
                    'details' => $activity['properties'],
                ];
            })
            ->toArray();
    }

    /**
     * Calculate auto-match rate.
     */
    private function calculateAutoMatchRate(BankReconciliation $reconciliation): float
    {
        $totalMatches = $reconciliation->matches()->count();
        $autoMatches = $reconciliation->matches()->where('auto_matched', true)->count();
        
        return $totalMatches > 0 ? round(($autoMatches / $totalMatches) * 100, 1) : 0;
    }

    /**
     * Calculate average confidence score.
     */
    private function calculateAverageConfidence(BankReconciliation $reconciliation): float
    {
        $matches = $reconciliation->matches()->whereNotNull('confidence_score');
        
        if ($matches->isEmpty()) {
            return 0;
        }
        
        return round($matches->avg('confidence_score'), 2);
    }

    /**
     * Calculate efficiency score.
     */
    private function calculateEfficiencyScore(BankReconciliation $reconciliation): int
    {
        $autoMatchRate = $this->calculateAutoMatchRate($reconciliation);
        $avgConfidence = $this->calculateAverageConfidence($reconciliation);
        $completeness = $reconciliation->percent_complete;
        
        // Weighted efficiency score
        return intval(($autoMatchRate * 0.4) + ($avgConfidence * 100 * 0.3) + ($completeness * 0.3));
    }

    /**
     * Get display name for adjustment type.
     */
    private function getAdjustmentTypeDisplayName(string $adjustmentType): string
    {
        return match ($adjustmentType) {
            'bank_fee' => 'Bank Fees',
            'interest' => 'Interest Income',
            'write_off' => 'Write Offs',
            'timing' => 'Timing Adjustments',
            default => ucfirst(str_replace('_', ' ', $adjustmentType)),
        };
    }

    /**
     * Log report generation for audit.
     */
    private function logReportGeneration(BankReconciliation $reconciliation, string $format, string $filename): void
    {
        $subscriber = new \Modules\Ledger\Listeners\BankReconciliationAuditSubscriber();
        $subscriber->logExportOperation($reconciliation, $format, [
            'filename' => $filename,
            'file_path' => $this->reportsPath . '/' . $filename,
        ]);
    }
}