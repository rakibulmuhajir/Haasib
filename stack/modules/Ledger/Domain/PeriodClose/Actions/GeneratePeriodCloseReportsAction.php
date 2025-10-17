<?php

namespace Modules\Ledger\Domain\PeriodClose\Actions;

use App\Models\AccountingPeriod;
use App\Models\User;
use Modules\Ledger\Services\PeriodCloseService;

class GeneratePeriodCloseReportsAction
{
    public function __construct(
        private PeriodCloseService $periodCloseService
    ) {}

    /**
     * Execute the report generation action.
     */
    public function execute(AccountingPeriod $period, array $reportTypes, User $user): string
    {
        // Validate inputs
        $this->validateInputs($period, $reportTypes, $user);

        // Generate the reports
        $reportId = $this->periodCloseService->generateReports(
            $period->id,
            $reportTypes,
            $user
        );

        return $reportId;
    }

    /**
     * Validate the inputs for report generation.
     */
    private function validateInputs(AccountingPeriod $period, array $reportTypes, User $user): void
    {
        // Validate period exists and has period close
        if (! $period->periodClose) {
            throw new \InvalidArgumentException('Period close not found for this period');
        }

        // Validate user permissions
        if (! $user->can('period-close.reports')) {
            throw new \Illuminate\Auth\Access\AuthorizationException('User does not have permission to generate reports');
        }

        // Validate report types
        $this->validateReportTypes($reportTypes);

        // Validate period status for specific report types
        $this->validatePeriodStatus($period, $reportTypes);

        // Validate company scoping
        $this->validateCompanyScoping($period, $user);
    }

    /**
     * Validate report types.
     */
    private function validateReportTypes(array $reportTypes): void
    {
        if (empty($reportTypes)) {
            throw new \InvalidArgumentException('At least one report type must be specified');
        }

        $validReportTypes = [
            'income_statement',
            'balance_sheet',
            'cash_flow',
            'trial_balance',
            'interim_trial_balance',
            'final_statements',
            'management_reports',
            'tax_reports',
        ];

        foreach ($reportTypes as $type) {
            if (! in_array($type, $validReportTypes)) {
                throw new \InvalidArgumentException("Invalid report type: {$type}");
            }
        }

        // Check for duplicate report types
        if (count($reportTypes) !== count(array_unique($reportTypes))) {
            throw new \InvalidArgumentException('Duplicate report types are not allowed');
        }

        // Validate report type combinations
        $this->validateReportTypeCombinations($reportTypes);
    }

    /**
     * Validate report type combinations for business logic rules.
     */
    private function validateReportTypeCombinations(array $reportTypes): void
    {
        // Rule: final_statements cannot be combined with interim reports
        if (in_array('final_statements', $reportTypes)) {
            $interimReports = ['interim_trial_balance', 'trial_balance'];
            $conflicts = array_intersect($reportTypes, $interimReports);
            if (! empty($conflicts)) {
                throw new \InvalidArgumentException('Final statements cannot be combined with interim reports');
            }
        }

        // Rule: tax_reports requires closed period (validated later but checked here for early feedback)
        if (in_array('tax_reports', $reportTypes)) {
            // This will be validated in validatePeriodStatus but we check here for better error context
        }

        // Rule: Limit number of reports per request for performance
        if (count($reportTypes) > 5) {
            throw new \InvalidArgumentException('Cannot generate more than 5 reports in a single request');
        }
    }

    /**
     * Validate period status for specific report types.
     */
    private function validatePeriodStatus(AccountingPeriod $period, array $reportTypes): void
    {
        $finalReportTypes = ['final_statements', 'tax_reports'];
        $hasFinalReports = array_intersect($reportTypes, $finalReportTypes);

        if ($hasFinalReports && $period->status !== 'closed') {
            $finalReportNames = implode(', ', $hasFinalReports);
            throw new \InvalidArgumentException("Final reports ({$finalReportNames}) require closed period. Current period status: {$period->status}");
        }

        // For trial balance and interim reports, period should at least be in review
        $interimReportTypes = ['trial_balance', 'interim_trial_balance'];
        $hasInterimReports = array_intersect($reportTypes, $interimReportTypes);

        if ($hasInterimReports && ! in_array($period->status, ['open', 'closing', 'closed'])) {
            throw new \InvalidArgumentException("Interim reports require period to be at least open. Current period status: {$period->status}");
        }
    }

    /**
     * Validate company scoping.
     */
    private function validateCompanyScoping(AccountingPeriod $period, User $user): void
    {
        // Check if user belongs to the period's company
        if (! $user->companies()->where('company_id', $period->company_id)->exists()) {
            throw new \Illuminate\Auth\Access\AuthorizationException('User does not have access to this company');
        }

        // Additional scoping validation can be added here
        // For example: checking specific permissions per company
    }

    /**
     * Get report generation options based on period and user context.
     */
    public function getReportOptions(AccountingPeriod $period, User $user): array
    {
        $periodClose = $period->periodClose;
        $isClosedPeriod = $period->status === 'closed';
        $periodCloseStatus = $periodClose?->status ?? 'not_started';

        $availableReports = [
            'income_statement' => [
                'label' => 'Income Statement',
                'description' => 'Revenue, expenses, and net income for the period',
                'available' => $periodCloseStatus !== 'not_started',
                'format' => 'pdf',
                'estimated_pages' => 2,
                'generation_time' => '30-60 seconds',
            ],
            'balance_sheet' => [
                'label' => 'Balance Sheet',
                'description' => 'Assets, liabilities, and equity at period end',
                'available' => $periodCloseStatus !== 'not_started',
                'format' => 'pdf',
                'estimated_pages' => 1,
                'generation_time' => '30-60 seconds',
            ],
            'cash_flow' => [
                'label' => 'Cash Flow Statement',
                'description' => 'Operating, investing, and financing activities',
                'available' => $periodCloseStatus !== 'not_started',
                'format' => 'pdf',
                'estimated_pages' => 1,
                'generation_time' => '45-90 seconds',
            ],
            'trial_balance' => [
                'label' => 'Trial Balance',
                'description' => 'Account balances with debits and credits',
                'available' => in_array($periodCloseStatus, ['in_review', 'awaiting_approval', 'locked', 'closed']),
                'format' => 'pdf',
                'estimated_pages' => 3,
                'generation_time' => '15-30 seconds',
            ],
            'interim_trial_balance' => [
                'label' => 'Interim Trial Balance',
                'description' => 'Current trial balance for review periods',
                'available' => in_array($periodCloseStatus, ['in_review', 'awaiting_approval']),
                'format' => 'pdf',
                'estimated_pages' => 2,
                'generation_time' => '15-30 seconds',
            ],
            'final_statements' => [
                'label' => 'Final Financial Statements',
                'description' => 'Complete set of audited financial statements',
                'available' => $isClosedPeriod && $periodCloseStatus === 'closed',
                'format' => 'pdf',
                'estimated_pages' => 8,
                'generation_time' => '2-5 minutes',
                'warning' => 'Only available for closed periods',
            ],
            'management_reports' => [
                'label' => 'Management Reports',
                'description' => 'Detailed operational and analytical reports',
                'available' => $periodCloseStatus !== 'not_started',
                'format' => 'pdf',
                'estimated_pages' => 15,
                'generation_time' => '3-8 minutes',
            ],
            'tax_reports' => [
                'label' => 'Tax Reports',
                'description' => 'Tax-specific financial data and schedules',
                'available' => $isClosedPeriod && $periodCloseStatus === 'closed',
                'format' => 'pdf',
                'estimated_pages' => 12,
                'generation_time' => '2-4 minutes',
                'warning' => 'Only available for closed periods',
            ],
        ];

        // Filter out unavailable reports if user doesn't have permission
        if (! $user->can('period-close.advanced_reports')) {
            unset($availableReports['management_reports']);
            unset($availableReports['tax_reports']);
        }

        return [
            'period_info' => [
                'id' => $period->id,
                'name' => $period->name,
                'status' => $period->status,
                'start_date' => $period->start_date->toDateString(),
                'end_date' => $period->end_date->toDateString(),
            ],
            'period_close_info' => [
                'status' => $periodCloseStatus,
                'started_at' => $periodClose?->started_at,
                'closed_at' => $periodClose?->closed_at,
            ],
            'available_reports' => $availableReports,
            'permissions' => [
                'can_generate_reports' => $user->can('period-close.reports'),
                'can_generate_advanced_reports' => $user->can('period-close.advanced_reports'),
                'can_download_reports' => $user->can('period-close.download'),
            ],
            'generation_limits' => [
                'max_reports_per_request' => 5,
                'max_concurrent_generations' => 2,
                'retention_days' => 90,
            ],
        ];
    }

    /**
     * Get report generation status and history.
     */
    public function getReportHistory(AccountingPeriod $period, User $user): array
    {
        if (! $user->can('period-close.reports')) {
            throw new \Illuminate\Auth\Access\AuthorizationException('User does not have permission to view reports');
        }

        $reports = $this->periodCloseService->getPeriodCloseReports($period->id);
        $status = $this->periodCloseService->getReportStatus($period->id);

        return [
            'current_status' => $status,
            'history' => $reports['reports'] ?? [],
            'summary' => [
                'total_reports' => $reports['total_reports'] ?? 0,
                'completed_reports' => count(array_filter($reports['reports'] ?? [], fn ($r) => $r['status'] === 'completed')),
                'processing_reports' => count(array_filter($reports['reports'] ?? [], fn ($r) => $r['status'] === 'processing')),
                'failed_reports' => count(array_filter($reports['reports'] ?? [], fn ($r) => $r['status'] === 'failed')),
            ],
        ];
    }

    /**
     * Cancel a report generation request.
     */
    public function cancelReportGeneration(string $reportId, User $user): bool
    {
        // This would typically update the report status to 'cancelled'
        // and notify the job handler to stop processing

        // Implementation would depend on your job queue system
        // For now, return true as a placeholder

        return true;
    }
}
