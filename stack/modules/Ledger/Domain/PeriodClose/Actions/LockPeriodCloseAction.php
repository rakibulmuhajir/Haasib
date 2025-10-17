<?php

namespace Modules\Ledger\Domain\PeriodClose\Actions;

use App\Models\User;
use App\Support\ServiceContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Ledger\Domain\PeriodClose\Exceptions\PeriodCloseException;
use Modules\Ledger\Domain\PeriodClose\Models\PeriodClose;
use Modules\Ledger\Services\PeriodCloseService;

class LockPeriodCloseAction
{
    public function __construct(
        private PeriodCloseService $periodCloseService
    ) {}

    /**
     * Lock a period close to prevent further modifications and prepare for completion.
     */
    public function execute(PeriodClose $periodClose, array $data, ServiceContext $context): bool
    {
        $reason = $data['reason'] ?? '';
        $user = User::findOrFail($context->userId);

        // Validate that the period close can be locked
        $this->validatePeriodCloseCanBeLocked($periodClose);

        try {
            DB::beginTransaction();

            // Lock the period close using the service
            $result = $this->periodCloseService->lockPeriodClose(
                $periodClose->id,
                $user,
                $reason
            );

            if (! $result) {
                throw new PeriodCloseException('Failed to lock period close');
            }

            // Log the lock action
            Log::info('Period close locked', [
                'period_close_id' => $periodClose->id,
                'accounting_period_id' => $periodClose->accounting_period_id,
                'locked_by' => $user->id,
                'lock_reason' => $reason,
                'context' => $context->toArray(),
            ]);

            DB::commit();

            return true;

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Failed to lock period close', [
                'period_close_id' => $periodClose->id,
                'error' => $e->getMessage(),
                'context' => $context->toArray(),
            ]);

            throw new PeriodCloseException('Failed to lock period close: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Validate that the period close can be locked.
     */
    private function validatePeriodCloseCanBeLocked(PeriodClose $periodClose): void
    {
        // Check period close status
        if (! in_array($periodClose->status, ['in_review', 'awaiting_approval'])) {
            throw new PeriodCloseException(
                "Cannot lock period close in status: {$periodClose->status}. ".
                "Status must be 'in_review' or 'awaiting_approval'"
            );
        }

        // Check if already locked
        if ($periodClose->status === 'locked') {
            throw new PeriodCloseException('Period close is already locked');
        }

        // Check if already completed
        if ($periodClose->status === 'closed') {
            throw new PeriodCloseException('Cannot lock a completed period close');
        }

        // Check accounting period status
        $accountingPeriod = $periodClose->accountingPeriod;
        if (! $accountingPeriod) {
            throw new PeriodCloseException('Period close has no associated accounting period');
        }

        if ($accountingPeriod->status !== 'open') {
            throw new PeriodCloseException(
                "Cannot lock period close: accounting period status is '{$accountingPeriod->status}'. ".
                "Accounting period must be 'open'"
            );
        }

        // Check if all required tasks are completed
        $this->validateRequiredTasksCompleted($periodClose);

        // Check if there are any blocking validation issues
        $this->validateNoBlockingIssues($periodClose);
    }

    /**
     * Validate that all required tasks are completed.
     */
    private function validateRequiredTasksCompleted(PeriodClose $periodClose): void
    {
        $incompleteRequiredTasks = $periodClose->tasks()
            ->where('is_required', true)
            ->where('status', '!=', 'completed')
            ->get();

        if ($incompleteRequiredTasks->isNotEmpty()) {
            $taskList = $incompleteRequiredTasks->map(function ($task) {
                return "{$task->code} ({$task->title})";
            })->join(', ');

            throw new PeriodCloseException(
                "Cannot lock period close: {$incompleteRequiredTasks->count()} required tasks not completed. ".
                "Incomplete tasks: {$taskList}"
            );
        }
    }

    /**
     * Validate that there are no blocking validation issues.
     */
    private function validateNoBlockingIssues(PeriodClose $periodClose): void
    {
        try {
            // Run validation to check for blocking issues
            $validationResults = $this->periodCloseService->validatePeriodClose(
                $periodClose->accounting_period_id
            );

            // Check for error-level issues that should prevent locking
            if (isset($validationResults['issues'])) {
                $errorIssues = collect($validationResults['issues'])
                    ->filter(fn ($issue) => ($issue['type'] ?? '') === 'error' && ($issue['priority'] ?? '') === 'high')
                    ->values();

                if ($errorIssues->isNotEmpty()) {
                    $issueDescriptions = $errorIssues->map(function ($issue) {
                        return $issue['message'] ?? 'Unknown error';
                    })->join('; ');

                    throw new PeriodCloseException(
                        "Cannot lock period close: {$errorIssues->count()} blocking validation issues found. ".
                        "Issues: {$issueDescriptions}"
                    );
                }
            }

            // Check validation score
            if (isset($validationResults['score']) && $validationResults['score'] < 80) {
                throw new PeriodCloseException(
                    "Cannot lock period close: validation score too low ({$validationResults['score']}%). ".
                    'Minimum score of 80% required to lock.'
                );
            }

        } catch (\Throwable $e) {
            // If validation fails due to system error, log but don't block locking
            Log::warning('Validation check failed during lock validation', [
                'period_close_id' => $periodClose->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check if a period close can be locked (read-only check).
     */
    public function canLock(PeriodClose $periodClose): array
    {
        $canLock = true;
        $blockingIssues = [];

        try {
            $this->validatePeriodCloseCanBeLocked($periodClose);
        } catch (PeriodCloseException $e) {
            $canLock = false;
            $blockingIssues[] = $e->getMessage();
        } catch (\Throwable $e) {
            $canLock = false;
            $blockingIssues[] = 'Unexpected error: '.$e->getMessage();
        }

        // Additional checks for informational purposes
        $taskStats = $periodClose->tasks()
            ->selectRaw('
                COUNT(*) as total_tasks,
                COUNT(CASE WHEN is_required = true THEN 1 END) as required_tasks,
                COUNT(CASE WHEN status = "completed" THEN 1 END) as completed_tasks,
                COUNT(CASE WHEN is_required = true AND status = "completed" THEN 1 END) as required_completed
            ')
            ->first();

        return [
            'can_lock' => $canLock,
            'blocking_issues' => $blockingIssues,
            'period_close_status' => $periodClose->status,
            'accounting_period_status' => $periodClose->accountingPeriod?->status,
            'task_statistics' => [
                'total_tasks' => $taskStats->total_tasks ?? 0,
                'required_tasks' => $taskStats->required_tasks ?? 0,
                'completed_tasks' => $taskStats->completed_tasks ?? 0,
                'required_completed' => $taskStats->required_completed ?? 0,
                'completion_rate' => $taskStats->total_tasks > 0
                    ? round(($taskStats->completed_tasks / $taskStats->total_tasks) * 100, 2)
                    : 100,
                'required_completion_rate' => $taskStats->required_tasks > 0
                    ? round(($taskStats->required_completed / $taskStats->required_tasks) * 100, 2)
                    : 100,
            ],
            'last_validation_score' => $this->getLastValidationScore($periodClose),
            'pending_adjustments_count' => $this->getPendingAdjustmentsCount($periodClose),
        ];
    }

    /**
     * Get the last validation score for a period close.
     */
    private function getLastValidationScore(PeriodClose $periodClose): ?int
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
     * Get the count of pending adjustments for a period close.
     */
    private function getPendingAdjustmentsCount(PeriodClose $periodClose): int
    {
        try {
            $adjustments = $this->periodCloseService->getPeriodCloseAdjustments($periodClose->id);

            return $adjustments->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
