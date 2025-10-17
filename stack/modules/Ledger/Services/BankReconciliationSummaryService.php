<?php

namespace Modules\Ledger\Services;

use App\Models\BankReconciliation;
use Illuminate\Support\Collection;

class BankReconciliationSummaryService
{
    public function calculateVariance(BankReconciliation $reconciliation): array
    {
        $statement = $reconciliation->statement;

        if (! $statement) {
            return [
                'opening_balance' => 0,
                'closing_balance' => 0,
                'total_statement_amount' => 0,
                'total_matched_amount' => 0,
                'total_adjustments' => 0,
                'unmatched_statement_total' => 0,
                'unmatched_internal_total' => 0,
                'variance' => 0,
                'variance_percentage' => 0,
                'is_balanced' => true,
            ];
        }

        $statementPeriodAmount = $statement->closing_balance - $statement->opening_balance;

        // Calculate matched amount from matches
        $totalMatches = $reconciliation->matches()->sum('amount');

        // Calculate adjustment total
        $totalAdjustments = $reconciliation->adjustments()->sum('amount');

        // Calculate unmatched amounts
        $unmatchedStatementTotal = $this->calculateUnmatchedStatementTotal($reconciliation);
        $unmatchedInternalTotal = $this->calculateUnmatchedInternalTotal($reconciliation);

        // Calculate variance
        $variance = $unmatchedStatementTotal - $unmatchedInternalTotal + $totalAdjustments;

        // Calculate variance percentage
        $variancePercentage = $statementPeriodAmount != 0
            ? ($variance / abs($statementPeriodAmount)) * 100
            : 0;

        return [
            'opening_balance' => $statement->opening_balance,
            'closing_balance' => $statement->closing_balance,
            'statement_period_amount' => $statementPeriodAmount,
            'total_statement_amount' => $this->getTotalStatementAmount($reconciliation),
            'total_matched_amount' => $totalMatches,
            'total_adjustments' => $totalAdjustments,
            'unmatched_statement_total' => $unmatchedStatementTotal,
            'unmatched_internal_total' => $unmatchedInternalTotal,
            'variance' => $variance,
            'variance_percentage' => $variancePercentage,
            'is_balanced' => abs($variance) <= 0.01, // Consider amounts within 1 cent as balanced
        ];
    }

    public function getSummaryStats(BankReconciliation $reconciliation): array
    {
        $variance = $this->calculateVariance($reconciliation);

        $statementLines = $reconciliation->statement->bankStatementLines ?? collect();
        $totalLines = $statementLines->count();
        $matchedLines = $reconciliation->matches()->count();
        $unmatchedLines = $totalLines - $matchedLines;

        $adjustments = $reconciliation->adjustments;
        $totalAdjustments = $adjustments->count();

        $autoMatches = $reconciliation->matches()->where('auto_matched', true)->count();
        $manualMatches = $matchedLines - $autoMatches;

        return [
            'statement_lines' => [
                'total' => $totalLines,
                'matched' => $matchedLines,
                'unmatched' => $unmatchedLines,
                'percentage_matched' => $totalLines > 0 ? round(($matchedLines / $totalLines) * 100, 1) : 0,
            ],
            'matches' => [
                'total' => $matchedLines,
                'auto_matched' => $autoMatches,
                'manual_matches' => $manualMatches,
                'auto_match_percentage' => $matchedLines > 0 ? round(($autoMatches / $matchedLines) * 100, 1) : 0,
            ],
            'adjustments' => [
                'total' => $totalAdjustments,
                'by_type' => $this->getAdjustmentsByType($adjustments),
                'total_amount' => $variance['total_adjustments'],
            ],
            'variance' => [
                'amount' => $variance['variance'],
                'formatted' => number_format($variance['variance'], 2),
                'percentage' => round($variance['variance_percentage'], 2),
                'status' => $this->getVarianceStatus($variance['variance']),
                'is_balanced' => $variance['is_balanced'],
            ],
            'period' => [
                'opening_balance' => number_format($variance['opening_balance'], 2),
                'closing_balance' => number_format($variance['closing_balance'], 2),
                'statement_amount' => number_format($variance['statement_period_amount'], 2),
            ],
            'completeness' => [
                'percentage' => $this->calculateCompletenessPercentage($reconciliation),
                'is_complete' => $this->isReconciliationComplete($reconciliation),
                'remaining_steps' => $this->getRemainingSteps($reconciliation),
            ],
        ];
    }

    public function getBreakdown(BankReconciliation $reconciliation): array
    {
        $variance = $this->calculateVariance($reconciliation);

        return [
            'statement_activity' => $this->getStatementActivityBreakdown($reconciliation),
            'matched_transactions' => $this->getMatchedTransactionsBreakdown($reconciliation),
            'adjustments' => $this->getAdjustmentsBreakdown($reconciliation),
            'variance_analysis' => $this->getVarianceAnalysis($reconciliation, $variance),
            'recommendations' => $this->getRecommendations($reconciliation, $variance),
        ];
    }

    private function getTotalStatementAmount(BankReconciliation $reconciliation): float
    {
        return $reconciliation->statement->bankStatementLines()->sum('amount');
    }

    private function calculateUnmatchedStatementTotal(BankReconciliation $reconciliation): float
    {
        $matchedStatementLineIds = $reconciliation->matches()->pluck('statement_line_id');

        return $reconciliation->statement->bankStatementLines()
            ->whereNotIn('id', $matchedStatementLineIds)
            ->sum('amount');
    }

    private function calculateUnmatchedInternalTotal(BankReconciliation $reconciliation): float
    {
        // This is a simplified calculation. In a real implementation,
        // you would need to calculate this based on what internal transactions
        // are available for matching but haven't been matched yet.

        // For now, we'll estimate based on typical business patterns
        $statementPeriodAmount = $reconciliation->statement->closing_balance - $reconciliation->statement->opening_balance;
        $matchedAmount = $reconciliation->matches()->sum('amount');
        $adjustmentAmount = $reconciliation->adjustments()->sum('amount');

        return $statementPeriodAmount - $matchedAmount - $adjustmentAmount;
    }

    private function getAdjustmentsByType(Collection $adjustments): array
    {
        return $adjustments->groupBy('adjustment_type')
            ->map(function ($adjustments) {
                return [
                    'count' => $adjustments->count(),
                    'total_amount' => number_format($adjustments->sum('amount'), 2),
                    'avg_amount' => $adjustments->avg('amount'),
                ];
            })
            ->toArray();
    }

    private function getVarianceStatus(float $variance): string
    {
        if (abs($variance) <= 0.01) {
            return 'balanced';
        } elseif ($variance > 0) {
            return 'positive';
        } else {
            return 'negative';
        }
    }

    private function calculateCompletenessPercentage(BankReconciliation $reconciliation): int
    {
        $statementLines = $reconciliation->statement->bankStatementLines ?? collect();
        $totalLines = $statementLines->count();

        if ($totalLines === 0) {
            return 0;
        }

        $matchedLines = $reconciliation->matches()->count();

        return intval(($matchedLines / $totalLines) * 100);
    }

    private function isReconciliationComplete(BankReconciliation $reconciliation): bool
    {
        $completeness = $this->calculateCompletenessPercentage($reconciliation);
        $variance = $this->calculateVariance($reconciliation);

        return $completeness >= 95 && $variance['is_balanced'];
    }

    private function getRemainingSteps(BankReconciliation $reconciliation): array
    {
        $steps = [];

        // Check if all statement lines are matched
        $completeness = $this->calculateCompletenessPercentage($reconciliation);
        if ($completeness < 100) {
            $steps[] = [
                'type' => 'matching',
                'description' => 'Match remaining statement lines',
                'percentage' => 100 - $completeness,
            ];
        }

        // Check if variance is resolved
        $variance = $this->calculateVariance($reconciliation);
        if (! $variance['is_balanced']) {
            $steps[] = [
                'type' => 'variance',
                'description' => 'Resolve variance through adjustments',
                'amount' => abs($variance['variance']),
            ];
        }

        return $steps;
    }

    private function getStatementActivityBreakdown(BankReconciliation $reconciliation): array
    {
        $statementLines = $reconciliation->statement->bankStatementLines()
            ->selectRaw('transaction_date, SUM(amount) as daily_amount, COUNT(*) as transaction_count')
            ->groupBy('transaction_date')
            ->orderBy('transaction_date')
            ->get();

        return $statementLines->map(function ($day) {
            return [
                'date' => $day->transaction_date->format('Y-m-d'),
                'amount' => number_format($day->daily_amount, 2),
                'transaction_count' => $day->transaction_count,
            ];
        })->toArray();
    }

    private function getMatchedTransactionsBreakdown(BankReconciliation $reconciliation): array
    {
        $matches = $reconciliation->matches()
            ->with('source')
            ->get();

        return $matches->groupBy('source_type')
            ->map(function ($groupedMatches, $sourceType) {
                return [
                    'source_type' => $sourceType,
                    'display_name' => $this->getSourceTypeDisplayName($sourceType),
                    'count' => $groupedMatches->count(),
                    'total_amount' => number_format($groupedMatches->sum('amount'), 2),
                    'auto_matched' => $groupedMatches->where('auto_matched', true)->count(),
                    'manual_matches' => $groupedMatches->where('auto_matched', false)->count(),
                ];
            })
            ->values()
            ->toArray();
    }

    private function getAdjustmentsBreakdown(BankReconciliation $reconciliation): array
    {
        return $reconciliation->adjustments()
            ->selectRaw('adjustment_type, COUNT(*) as count, SUM(amount) as total_amount')
            ->groupBy('adjustment_type')
            ->orderBy('adjustment_type')
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

    private function getVarianceAnalysis(BankReconciliation $reconciliation, array $variance): array
    {
        return [
            'current_variance' => [
                'amount' => $variance['variance'],
                'formatted' => number_format($variance['variance'], 2),
                'percentage' => round($variance['variance_percentage'], 2),
                'status' => $this->getVarianceStatus($variance['variance']),
            ],
            'components' => [
                'unmatched_statement' => [
                    'amount' => $variance['unmatched_statement_total'],
                    'formatted' => number_format($variance['unmatched_statement_total'], 2),
                ],
                'unmatched_internal' => [
                    'amount' => $variance['unmatched_internal_total'],
                    'formatted' => number_format($variance['unmatched_internal_total'], 2),
                ],
                'adjustments' => [
                    'amount' => $variance['total_adjustments'],
                    'formatted' => number_format($variance['total_adjustments'], 2),
                ],
            ],
            'balance_comparison' => [
                'statement_period_amount' => [
                    'amount' => $variance['statement_period_amount'],
                    'formatted' => number_format($variance['statement_period_amount'], 2),
                ],
                'reconciled_amount' => [
                    'amount' => $variance['statement_period_amount'] - $variance['variance'],
                    'formatted' => number_format($variance['statement_period_amount'] - $variance['variance'], 2),
                ],
            ],
        ];
    }

    private function getRecommendations(BankReconciliation $reconciliation, array $variance): array
    {
        $recommendations = [];

        if (abs($variance['variance']) > 0.01) {
            $recommendations[] = [
                'type' => 'variance',
                'priority' => 'high',
                'title' => 'Resolve Variance',
                'description' => 'Create adjustments or match remaining transactions to resolve the variance of '.
                               number_format(abs($variance['variance']), 2),
                'action' => abs($variance['variance']) > 0 ? 'Create debit adjustment' : 'Create credit adjustment',
            ];
        }

        $completeness = $this->calculateCompletenessPercentage($reconciliation);
        if ($completeness < 100) {
            $recommendations[] = [
                'type' => 'matching',
                'priority' => 'medium',
                'title' => 'Complete Matching',
                'description' => 'Match the remaining '.(100 - $completeness).'% of statement lines to internal transactions.',
                'action' => 'Run auto-match or create manual matches',
            ];
        }

        if ($recommendations === []) {
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

    private function getSourceTypeDisplayName(string $sourceType): string
    {
        $displayNames = [
            'ledger.journal_entry' => 'Journal Entries',
            'acct.payment' => 'Payments',
            'acct.invoice' => 'Invoices',
            'acct.credit_note' => 'Credit Notes',
        ];

        return $displayNames[$sourceType] ?? $sourceType;
    }

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
}
