<?php

namespace Modules\Ledger\Domain\PeriodClose\Actions;

use App\Models\AccountingPeriod;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Modules\Ledger\Domain\PeriodClose\Exceptions\PeriodCloseException;
use Modules\Ledger\Domain\PeriodClose\Models\PeriodClose;
use Modules\Ledger\Services\PeriodCloseService;

class ValidatePeriodCloseAction
{
    public function __construct(
        private PeriodCloseService $periodCloseService
    ) {}

    /**
     * Run comprehensive validations for the period close.
     */
    public function execute(AccountingPeriod $period, ?User $user = null): array
    {
        // Validate user access
        $user = $user ?? Auth::user();
        if (! $user) {
            throw PeriodCloseException::unauthorized('validate period close');
        }

        // Verify user has permission to validate period close
        if (! $user->can('period-close.validate')) {
            throw PeriodCloseException::unauthorized('validate period close');
        }

        // Ensure user belongs to the same company
        if (! $user->companies()->where('company_id', $period->company_id)->exists()) {
            throw PeriodCloseException::unauthorized('access company period close');
        }

        // Get the period close record
        $periodClose = $period->periodClose()->first();
        if (! $periodClose) {
            throw new PeriodCloseException("No period close found for period {$period->id}. Start the period close first.");
        }

        // Validate that period is in a valid state for validation
        if (! in_array($periodClose->status, ['in_review', 'awaiting_approval'])) {
            throw new PeriodCloseException("Cannot validate period close in status: {$periodClose->status}");
        }

        try {
            Log::info('Starting period close validation', [
                'period_id' => $period->id,
                'period_close_id' => $periodClose->id,
                'user_id' => $user->id,
                'company_id' => $period->company_id,
            ]);

            // Run the validation
            $validation = $this->periodCloseService->validatePeriodClose($period->id);

            // Enhance validation results with additional metadata
            $enhancedValidation = $this->enhanceValidationResults($validation, $periodClose, $user);

            // Emit validation completed event
            Event::dispatch('period-close.validated', [
                'period_close_id' => $periodClose->id,
                'accounting_period_id' => $period->id,
                'company_id' => $period->company_id,
                'user_id' => $user->id,
                'validation_results' => $enhancedValidation,
                'has_blocking_issues' => $enhancedValidation['summary']['has_blocking_issues'],
                'trial_balance_variance' => $enhancedValidation['summary']['trial_balance_variance']['amount'],
            ]);

            Log::info('Period close validation completed', [
                'period_id' => $period->id,
                'period_close_id' => $periodClose->id,
                'user_id' => $user->id,
                'has_blocking_issues' => $enhancedValidation['summary']['has_blocking_issues'],
                'trial_balance_variance' => $enhancedValidation['summary']['trial_balance_variance']['amount'],
                'unposted_count' => $enhancedValidation['summary']['blocking_count'],
            ]);

            return $enhancedValidation;
        } catch (\Exception $e) {
            Log::error('Period close validation failed', [
                'period_id' => $period->id,
                'period_close_id' => $periodClose->id,
                'user_id' => $user->id,
                'company_id' => $period->company_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new PeriodCloseException('Failed to validate period close: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Enhance validation results with additional metadata and formatting.
     */
    private function enhanceValidationResults(array $validation, PeriodClose $periodClose, User $user): array
    {
        $enhanced = $validation;

        // Add validation metadata
        $enhanced['validation_metadata'] = [
            'validated_at' => now()->toISOString(),
            'validated_by' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'period_close_id' => $periodClose->id,
            'period_close_status' => $periodClose->status,
        ];

        // Format and categorize unposted documents
        $enhanced['unposted_documents'] = $this->formatUnpostedDocuments($validation['unposted_documents'] ?? []);

        // Format warnings with severity levels
        $enhanced['warnings'] = $this->formatWarnings($validation['warnings'] ?? []);

        // Add trial balance analysis
        $enhanced['trial_balance_analysis'] = $this->analyzeTrialBalance($validation['trial_balance_variance'] ?? 0);

        // Add business logic recommendations
        $enhanced['recommendations'] = $this->generateRecommendations($enhanced);

        // Add validation summary
        $enhanced['summary'] = $this->createValidationSummary($enhanced);

        // Add next steps based on validation results
        $enhanced['next_steps'] = $this->generateNextSteps($enhanced, $periodClose);

        return $enhanced;
    }

    /**
     * Format unposted documents by category and add details.
     */
    private function formatUnpostedDocuments(array $unpostedDocuments): array
    {
        $formatted = [];

        foreach ($unpostedDocuments as $document) {
            $category = match ($document['module']) {
                'invoicing' => 'Customer Invoices',
                'purchasing' => 'Vendor Bills',
                'ledger' => 'Journal Entries',
                'payments' => 'Payment Transactions',
                'expenses' => 'Expense Reports',
                default => ucfirst($document['module']),
            };

            $formatted[] = [
                'category' => $category,
                'module' => $document['module'],
                'count' => $document['count'],
                'blocking' => $document['blocking'],
                'severity' => $document['blocking'] ? 'error' : 'warning',
                'description' => $this->getDocumentDescription($document),
                'details' => $document['details'] ?? [],
                'actions' => $this->getDocumentActions($document),
            ];
        }

        // Group by category
        return collect($formatted)->groupBy('category')->map(function ($items) {
            return [
                'category' => $items->first()['category'],
                'total_count' => $items->sum('count'),
                'blocking_count' => $items->where('blocking', true)->sum('count'),
                'blocking' => $items->where('blocking', true)->isNotEmpty(),
                'severity' => $items->where('blocking', true)->isNotEmpty() ? 'error' : 'warning',
                'items' => $items->toArray(),
            ];
        })->values()->toArray();
    }

    /**
     * Format warnings with severity and actionable information.
     */
    private function formatWarnings(array $warnings): array
    {
        return collect($warnings)->map(function ($warning) {
            return [
                'message' => $warning,
                'severity' => 'warning',
                'type' => $this->categorizeWarning($warning),
                'actions' => $this->getWarningActions($warning),
            ];
        })->toArray();
    }

    /**
     * Analyze trial balance variance.
     */
    private function analyzeTrialBalance(float $variance): array
    {
        return [
            'variance_amount' => $variance,
            'is_balanced' => abs($variance) < 0.01, // Allow for rounding
            'variance_percentage' => $this->calculateVariancePercentage($variance),
            'severity' => abs($variance) < 0.01 ? 'success' : (abs($variance) < 1.00 ? 'warning' : 'error'),
            'description' => $this->getTrialBalanceDescription($variance),
            'recommendations' => $this->getTrialBalanceRecommendations($variance),
        ];
    }

    /**
     * Generate actionable recommendations based on validation results.
     */
    private function generateRecommendations(array $validation): array
    {
        $recommendations = [];

        // Trial balance recommendations
        if (abs($validation['trial_balance_variance'] ?? 0) >= 0.01) {
            $recommendations[] = [
                'type' => 'trial_balance',
                'priority' => 'high',
                'title' => 'Balance Trial Balance',
                'description' => 'Review and correct journal entries to eliminate the trial balance variance',
                'actions' => ['Review journal entries', 'Check posting accuracy', 'Verify account balances'],
            ];
        }

        // Unposted documents recommendations
        if (! empty($validation['unposted_documents'])) {
            foreach ($validation['unposted_documents'] as $document) {
                if ($document['blocking']) {
                    $recommendations[] = [
                        'type' => 'unposted_documents',
                        'priority' => 'high',
                        'title' => "Post {$document['module']} Documents",
                        'description' => "Post {$document['count']} unposted {$document['module']} documents before closing",
                        'actions' => $document['details'] ?? ['Review and post documents'],
                    ];
                }
            }
        }

        // Warnings recommendations
        if (! empty($validation['warnings'])) {
            $recommendations[] = [
                'type' => 'warnings',
                'priority' => 'medium',
                'title' => 'Address Validation Warnings',
                'description' => 'Review and resolve validation warnings to ensure data integrity',
                'actions' => ['Review warning details', 'Investigate root causes', 'Document resolutions'],
            ];
        }

        return $recommendations;
    }

    /**
     * Create a summary of validation results.
     */
    private function createValidationSummary(array $validation): array
    {
        $blockingCount = collect($validation['unposted_documents'] ?? [])->sum('blocking_count');
        $totalUnposted = collect($validation['unposted_documents'] ?? [])->sum('total_count');

        return [
            'is_valid' => $blockingCount === 0 && abs($validation['trial_balance_variance'] ?? 0) < 0.01,
            'has_blocking_issues' => $blockingCount > 0 || abs($validation['trial_balance_variance'] ?? 0) >= 0.01,
            'trial_balance_variance' => $validation['trial_balance_analysis'],
            'unposted_documents_summary' => [
                'total_count' => $totalUnposted,
                'blocking_count' => $blockingCount,
                'categories_count' => count($validation['unposted_documents'] ?? []),
            ],
            'warnings_count' => count($validation['warnings'] ?? []),
            'recommendations_count' => count($validation['recommendations'] ?? []),
            'overall_status' => $this->getOverallValidationStatus($validation),
        ];
    }

    /**
     * Generate next steps based on validation results.
     */
    private function generateNextSteps(array $validation, PeriodClose $periodClose): array
    {
        $nextSteps = [];

        if ($validation['summary']['has_blocking_issues']) {
            $nextSteps[] = [
                'action' => 'resolve_blocking_issues',
                'title' => 'Resolve Blocking Issues',
                'description' => 'Address all blocking validation issues before proceeding',
                'priority' => 'high',
                'required' => true,
            ];
        } else {
            $nextSteps[] = [
                'action' => 'review_warnings',
                'title' => 'Review Warnings',
                'description' => 'Review any non-blocking warnings',
                'priority' => 'medium',
                'required' => false,
            ];

            if ($periodClose->allRequiredTasksCompleted()) {
                $nextSteps[] = [
                    'action' => 'submit_for_approval',
                    'title' => 'Submit for Approval',
                    'description' => 'All validations passed and required tasks completed',
                    'priority' => 'high',
                    'required' => false,
                ];
            } else {
                $nextSteps[] = [
                    'action' => 'complete_required_tasks',
                    'title' => 'Complete Required Tasks',
                    'description' => 'Finish all required checklist tasks',
                    'priority' => 'high',
                    'required' => true,
                ];
            }
        }

        return $nextSteps;
    }

    // Helper methods for formatting and categorization
    private function getDocumentDescription(array $document): string
    {
        $descriptions = [
            'invoicing' => 'Unposted customer invoices that need to be reviewed and posted',
            'purchasing' => 'Unposted vendor bills that require approval and posting',
            'ledger' => 'Unposted journal entries awaiting review and posting',
            'payments' => 'Pending payment transactions that need allocation',
            'expenses' => 'Expense reports awaiting approval and posting',
        ];

        return $descriptions[$document['module']] ?? "Unposted {$document['module']} documents";
    }

    private function getDocumentActions(array $document): array
    {
        if ($document['module'] === 'invoicing') {
            return ['Review invoices', 'Post approved invoices', 'Void invalid invoices'];
        } elseif ($document['module'] === 'ledger') {
            return ['Review journal entries', 'Post balanced entries', 'Correct errors'];
        } elseif ($document['module'] === 'payments') {
            return ['Allocate payments', 'Apply to invoices', 'Record receipts'];
        }

        return ['Review documents', 'Post valid items', 'Resolve issues'];
    }

    private function categorizeWarning(string $warning): string
    {
        if (str_contains(strtolower($warning), 'balance')) {
            return 'balance';
        } elseif (str_contains(strtolower($warning), 'reconcile')) {
            return 'reconciliation';
        } elseif (str_contains(strtolower($warning), 'date')) {
            return 'date';
        }

        return 'general';
    }

    private function getWarningActions(string $warning): array
    {
        return ['Review warning details', 'Investigate root cause', 'Document resolution'];
    }

    private function calculateVariancePercentage(float $variance): ?float
    {
        // This would typically calculate variance relative to total balances
        // For now, return null since we don't have the total balance context
        return null;
    }

    private function getTrialBalanceDescription(float $variance): string
    {
        if (abs($variance) < 0.01) {
            return 'Trial balance is properly balanced';
        } elseif (abs($variance) < 1.00) {
            return 'Minor variance detected, likely due to rounding';
        } else {
            return 'Significant trial balance variance requires investigation';
        }
    }

    private function getTrialBalanceRecommendations(float $variance): array
    {
        if (abs($variance) < 0.01) {
            return ['No action required'];
        } elseif (abs($variance) < 1.00) {
            return ['Review for rounding errors', 'Check decimal precision'];
        } else {
            return ['Review journal entries', 'Check posting accuracy', 'Verify account balances', 'Investigate errors'];
        }
    }

    private function getOverallValidationStatus(array $validation): string
    {
        if ($validation['summary']['has_blocking_issues']) {
            return 'failed';
        } elseif (! empty($validation['warnings'])) {
            return 'warning';
        } else {
            return 'passed';
        }
    }
}
