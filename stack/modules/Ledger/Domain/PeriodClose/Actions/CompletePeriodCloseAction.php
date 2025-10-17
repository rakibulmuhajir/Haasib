<?php

namespace Modules\Ledger\Domain\PeriodClose\Actions;

use App\Models\User;
use App\Support\ServiceContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Ledger\Domain\PeriodClose\Exceptions\PeriodCloseException;
use Modules\Ledger\Domain\PeriodClose\Models\PeriodClose;
use Modules\Ledger\Services\PeriodCloseService;

class CompletePeriodCloseAction
{
    public function __construct(
        private PeriodCloseService $periodCloseService
    ) {}

    /**
     * Complete a period close, closing the accounting period and finalizing all records.
     */
    public function execute(PeriodClose $periodClose, array $data, ServiceContext $context): bool
    {
        $notes = $data['notes'] ?? '';
        $user = User::findOrFail($context->userId);

        // Validate that the period close can be completed
        $this->validatePeriodCloseCanBeCompleted($periodClose);

        try {
            DB::beginTransaction();

            // Complete the period close using the service
            $result = $this->periodCloseService->completePeriodClose(
                $periodClose->id,
                $user,
                $notes
            );

            if (! $result) {
                throw new PeriodCloseException('Failed to complete period close');
            }

            // Log the completion action
            Log::info('Period close completed', [
                'period_close_id' => $periodClose->id,
                'accounting_period_id' => $periodClose->accounting_period_id,
                'completed_by' => $user->id,
                'completion_notes' => $notes,
                'context' => $context->toArray(),
            ]);

            DB::commit();

            return true;

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Failed to complete period close', [
                'period_close_id' => $periodClose->id,
                'error' => $e->getMessage(),
                'context' => $context->toArray(),
            ]);

            throw new PeriodCloseException('Failed to complete period close: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Validate that the period close can be completed.
     */
    private function validatePeriodCloseCanBeCompleted(PeriodClose $periodClose): void
    {
        // Check period close status
        if ($periodClose->status !== 'locked') {
            throw new PeriodCloseException(
                "Cannot complete period close in status: {$periodClose->status}. ".
                "Period close must be 'locked' before completion"
            );
        }

        // Check if already completed
        if ($periodClose->status === 'closed') {
            throw new PeriodCloseException('Period close is already completed');
        }

        // Check accounting period status
        $accountingPeriod = $periodClose->accountingPeriod;
        if (! $accountingPeriod) {
            throw new PeriodCloseException('Period close has no associated accounting period');
        }

        if ($accountingPeriod->status !== 'open') {
            throw new PeriodCloseException(
                "Cannot complete period close: accounting period status is '{$accountingPeriod->status}'. ".
                "Accounting period must be 'open'"
            );
        }

        // Validate lock was performed by appropriate authority
        $this->validateLockAuthority($periodClose);

        // Validate final conditions before completion
        $this->validateFinalCompletionConditions($periodClose);
    }

    /**
     * Validate that the lock was performed by an authorized user.
     */
    private function validateLockAuthority(PeriodClose $periodClose): void
    {
        if (! $periodClose->locked_at || ! $periodClose->locked_by) {
            throw new PeriodCloseException('Period close must be properly locked before completion');
        }

        // Check if lock is recent (within 24 hours) - configurable business rule
        $lockAge = now()->diffInHours($periodClose->locked_at);
        $maxLockAge = config('ledger.period_close.max_lock_age_hours', 72);

        if ($lockAge > $maxLockAge) {
            throw new PeriodCloseException(
                "Cannot complete period close: lock is too old ({$lockAge} hours). ".
                "Maximum lock age is {$maxLockAge} hours. Please re-lock the period."
            );
        }
    }

    /**
     * Validate final conditions before completing the period close.
     */
    private function validateFinalCompletionConditions(PeriodClose $periodClose): void
    {
        // Ensure all tasks are still completed (no changes since locking)
        $incompleteTasks = $periodClose->tasks()
            ->where('status', '!=', 'completed')
            ->count();

        if ($incompleteTasks > 0) {
            throw new PeriodCloseException(
                "Cannot complete period close: {$incompleteTasks} tasks are not completed. ".
                'All tasks must be completed before final completion.'
            );
        }

        // Check for any pending or unposted journal entries in the period
        $this->validateNoPendingJournalEntries($periodClose);

        // Run final validation check
        $this->validateFinalPeriodCloseValidation($periodClose);
    }

    /**
     * Validate that there are no pending journal entries in the period.
     */
    private function validateNoPendingJournalEntries(PeriodClose $periodClose): void
    {
        $accountingPeriod = $periodClose->accountingPeriod;

        // Check for unposted journal entries in the period
        $unpostedEntries = \App\Models\JournalEntry::where('company_id', $accountingPeriod->company_id)
            ->where('status', '!=', 'posted')
            ->whereBetween('entry_date', [$accountingPeriod->start_date, $accountingPeriod->end_date])
            ->count();

        if ($unpostedEntries > 0) {
            throw new PeriodCloseException(
                "Cannot complete period close: {$unpostedEntries} journal entries are still pending. ".
                'All entries must be posted before period completion.'
            );
        }
    }

    /**
     * Run final validation check before completing the period close.
     */
    private function validateFinalPeriodCloseValidation(PeriodClose $periodClose): void
    {
        try {
            $validationResults = $this->periodCloseService->validatePeriodClose(
                $periodClose->accounting_period_id
            );

            // Check for any remaining error-level issues
            if (isset($validationResults['issues'])) {
                $errorIssues = collect($validationResults['issues'])
                    ->filter(fn ($issue) => ($issue['type'] ?? '') === 'error')
                    ->values();

                if ($errorIssues->isNotEmpty()) {
                    $issueDescriptions = $errorIssues->map(function ($issue) {
                        return $issue['message'] ?? 'Unknown error';
                    })->join('; ');

                    throw new PeriodCloseException(
                        "Cannot complete period close: {$errorIssues->count()} validation issues found. ".
                        "Issues: {$issueDescriptions}"
                    );
                }
            }

            // Check final validation score
            $requiredScore = config('ledger.period_close.min_completion_score', 90);
            if (isset($validationResults['score']) && $validationResults['score'] < $requiredScore) {
                throw new PeriodCloseException(
                    "Cannot complete period close: validation score too low ({$validationResults['score']}%). ".
                    "Minimum score of {$requiredScore}% required to complete."
                );
            }

        } catch (\Throwable $e) {
            // If validation fails due to system error, log and potentially continue
            // based on business requirements
            Log::warning('Final validation check failed during period completion', [
                'period_close_id' => $periodClose->id,
                'error' => $e->getMessage(),
            ]);

            // For safety, block completion if validation is unavailable
            if (config('ledger.period_close.require_final_validation', true)) {
                throw new PeriodCloseException(
                    'Cannot complete period close: final validation check failed. '.
                    'Please ensure validation system is available.'
                );
            }
        }
    }

    /**
     * Check if a period close can be completed (read-only check).
     */
    public function canComplete(PeriodClose $periodClose): array
    {
        $canComplete = true;
        $blockingIssues = [];

        try {
            $this->validatePeriodCloseCanBeCompleted($periodClose);
        } catch (PeriodCloseException $e) {
            $canComplete = false;
            $blockingIssues[] = $e->getMessage();
        } catch (\Throwable $e) {
            $canComplete = false;
            $blockingIssues[] = 'Unexpected error: '.$e->getMessage();
        }

        // Additional completion-specific checks
        $lockAgeHours = $periodClose->locked_at ? now()->diffInHours($periodClose->locked_at) : null;
        $maxLockAge = config('ledger.period_close.max_lock_age_hours', 72);

        $taskStats = $periodClose->tasks()
            ->selectRaw('
                COUNT(*) as total_tasks,
                COUNT(CASE WHEN status = "completed" THEN 1 END) as completed_tasks
            ')
            ->first();

        return [
            'can_complete' => $canComplete,
            'blocking_issues' => $blockingIssues,
            'period_close_status' => $periodClose->status,
            'accounting_period_status' => $periodClose->accountingPeriod?->status,
            'lock_information' => [
                'locked_at' => $periodClose->locked_at?->toISOString(),
                'locked_by' => $periodClose->locked_by,
                'lock_age_hours' => $lockAgeHours,
                'max_lock_age_hours' => $maxLockAge,
                'lock_expired' => $lockAgeHours && $lockAgeHours > $maxLockAge,
                'lock_reason' => $periodClose->lock_reason,
            ],
            'task_statistics' => [
                'total_tasks' => $taskStats->total_tasks ?? 0,
                'completed_tasks' => $taskStats->completed_tasks ?? 0,
                'completion_rate' => $taskStats->total_tasks > 0
                    ? round(($taskStats->completed_tasks / $taskStats->total_tasks) * 100, 2)
                    : 100,
            ],
            'final_validation_score' => $this->getFinalValidationScore($periodClose),
            'pending_journal_entries' => $this->getPendingJournalEntriesCount($periodClose),
            'total_adjustments' => $this->getTotalAdjustmentsCount($periodClose),
            'completion_metadata' => [
                'estimated_completion_time' => $this->estimateCompletionTime($periodClose),
                'system_health' => $this->checkSystemHealth(),
            ],
        ];
    }

    /**
     * Get the final validation score for a period close.
     */
    private function getFinalValidationScore(PeriodClose $periodClose): ?int
    {
        try {
            $validationResults = $this->periodCloseService->validatePeriodClose(
                $periodClose->accounting_period_id
            );

            return $validationResults['score'] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Get the count of pending journal entries in the period.
     */
    private function getPendingJournalEntriesCount(PeriodClose $periodClose): int
    {
        try {
            $accountingPeriod = $periodClose->accountingPeriod;

            return \App\Models\JournalEntry::where('company_id', $accountingPeriod->company_id)
                ->where('status', '!=', 'posted')
                ->whereBetween('entry_date', [$accountingPeriod->start_date, $accountingPeriod->end_date])
                ->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Get the total count of adjustments for the period close.
     */
    private function getTotalAdjustmentsCount(PeriodClose $periodClose): int
    {
        try {
            $adjustments = $this->periodCloseService->getPeriodCloseAdjustments($periodClose->id);

            return $adjustments->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Estimate the time required to complete the period close.
     */
    private function estimateCompletionTime(PeriodClose $periodClose): array
    {
        $baseTimeMinutes = 5; // Base time for database operations
        $adjustmentsCount = $this->getTotalAdjustmentsCount($periodClose);
        $adjustmentTimeMinutes = $adjustmentsCount * 0.5; // 30 seconds per adjustment

        $estimatedMinutes = $baseTimeMinutes + $adjustmentTimeMinutes;

        return [
            'estimated_minutes' => round($estimatedMinutes, 1),
            'base_time_minutes' => $baseTimeMinutes,
            'adjustments_count' => $adjustmentsCount,
            'adjustment_time_minutes' => round($adjustmentTimeMinutes, 1),
        ];
    }

    /**
     * Check system health before completion.
     */
    private function checkSystemHealth(): array
    {
        return [
            'database_connection' => $this->checkDatabaseConnection(),
            'disk_space' => $this->checkDiskSpace(),
            'memory_usage' => $this->checkMemoryUsage(),
        ];
    }

    /**
     * Check database connection health.
     */
    private function checkDatabaseConnection(): bool
    {
        try {
            \DB::connection()->getPdo();

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Check available disk space.
     */
    private function checkDiskSpace(): bool
    {
        $freeSpace = disk_free_space('/');
        $requiredSpace = 100 * 1024 * 1024; // 100MB minimum

        return $freeSpace > $requiredSpace;
    }

    /**
     * Check memory usage.
     */
    private function checkMemoryUsage(): bool
    {
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));

        return $memoryUsage < ($memoryLimit * 0.8); // Must be under 80% of limit
    }

    /**
     * Parse PHP memory limit string to bytes.
     */
    private function parseMemoryLimit(string $limit): int
    {
        $limit = strtolower(trim($limit));
        $multiplier = 1;

        if (str_ends_with($limit, 'g')) {
            $multiplier = 1024 * 1024 * 1024;
            $limit = substr($limit, 0, -1);
        } elseif (str_ends_with($limit, 'm')) {
            $multiplier = 1024 * 1024;
            $limit = substr($limit, 0, -1);
        } elseif (str_ends_with($limit, 'k')) {
            $multiplier = 1024;
            $limit = substr($limit, 0, -1);
        }

        return (int) $limit * $multiplier;
    }
}
