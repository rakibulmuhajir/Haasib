<?php

namespace Modules\Ledger\Domain\PeriodClose\Actions;

use App\Models\AccountingPeriod;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Modules\Ledger\Domain\PeriodClose\Exceptions\PeriodCloseException;
use Modules\Ledger\Domain\PeriodClose\Models\PeriodClose;
use Modules\Ledger\Services\PeriodCloseService;

class GetPeriodCloseSnapshotAction
{
    public function __construct(
        private PeriodCloseService $periodCloseService
    ) {}

    /**
     * Get a comprehensive snapshot of the period close status.
     */
    public function execute(AccountingPeriod $period, ?User $user = null): array
    {
        // Validate user access
        $user = $user ?? Auth::user();
        if (! $user) {
            throw PeriodCloseException::unauthorized('access period close snapshot');
        }

        // Verify user has permission to view period close
        if (! $user->can('period-close.view')) {
            throw PeriodCloseException::unauthorized('view period close');
        }

        // Ensure user belongs to the same company
        if (! $user->companies()->where('company_id', $period->company_id)->exists()) {
            throw PeriodCloseException::unauthorized('access company period close');
        }

        try {
            $snapshot = $this->periodCloseService->getPeriodCloseSnapshot($period->id);

            // Enhance snapshot with additional metadata
            return $this->enhanceSnapshot($snapshot, $period, $user);
        } catch (\Exception $e) {
            throw new PeriodCloseException('Failed to retrieve period close snapshot: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Enhance the basic snapshot with additional metadata and permissions.
     */
    private function enhanceSnapshot(array $snapshot, AccountingPeriod $period, User $user): array
    {
        $enhanced = $snapshot;

        // Add period metadata
        $enhanced['period_metadata'] = [
            'name' => $period->name ?? "Period ending {$period->end_date->format('Y-m-d')}",
            'start_date' => $period->start_date->format('Y-m-d'),
            'end_date' => $period->end_date->format('Y-m-d'),
            'fiscal_year' => [
                'id' => $period->fiscal_year->id,
                'name' => $period->fiscal_year->name,
                'start_date' => $period->fiscal_year->start_date->format('Y-m-d'),
                'end_date' => $period->fiscal_year->end_date->format('Y-m-d'),
            ],
            'duration_days' => $period->start_date->diffInDays($period->end_date) + 1,
        ];

        // Add user permissions
        $enhanced['permissions'] = $this->getUserPermissions($user, $snapshot['status']);

        // Add task statistics
        $enhanced['task_statistics'] = $this->calculateTaskStatistics($snapshot['tasks']);

        // Add validation summary if available
        if (isset($snapshot['validation_summary'])) {
            $enhanced['validation_summary'] = $this->formatValidationSummary($snapshot['validation_summary']);
        }

        // Add workflow actions available
        $enhanced['available_actions'] = $this->getAvailableActions($user, $snapshot['status'], $enhanced['task_statistics']);

        // Add recent activity if period close exists
        if ($snapshot['period_close']) {
            $enhanced['recent_activity'] = $this->getRecentActivity($snapshot['period_close']);
        }

        return $enhanced;
    }

    /**
     * Get user permissions for the period close.
     */
    private function getUserPermissions(User $user, string $status): array
    {
        return [
            'can_view' => $user->can('period-close.view'),
            'can_start' => $user->can('period-close.start') && $status === 'not_started',
            'can_validate' => $user->can('period-close.validate') && in_array($status, ['in_review', 'awaiting_approval']),
            'can_lock' => $user->can('period-close.lock') && in_array($status, ['awaiting_approval']),
            'can_complete' => $user->can('period-close.complete') && $status === 'locked',
            'can_reopen' => $user->can('period-close.reopen') && $status === 'closed',
            'can_adjust' => $user->can('period-close.adjust') && in_array($status, ['in_review', 'awaiting_approval', 'locked']),
        ];
    }

    /**
     * Calculate task statistics.
     */
    private function calculateTaskStatistics($tasks): array
    {
        if (empty($tasks) || $tasks->isEmpty()) {
            return [
                'total' => 0,
                'completed' => 0,
                'pending' => 0,
                'required_completed' => 0,
                'required_total' => 0,
                'completion_percentage' => 0,
                'required_completion_percentage' => 0,
            ];
        }

        $total = $tasks->count();
        $completed = $tasks->where('status', 'completed')->count();
        $pending = $total - $completed;
        $requiredTotal = $tasks->where('is_required', true)->count();
        $requiredCompleted = $tasks->where('is_required', true)->where('status', 'completed')->count();

        return [
            'total' => $total,
            'completed' => $completed,
            'pending' => $pending,
            'required_completed' => $requiredCompleted,
            'required_total' => $requiredTotal,
            'completion_percentage' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
            'required_completion_percentage' => $requiredTotal > 0 ? round(($requiredCompleted / $requiredTotal) * 100, 1) : 0,
        ];
    }

    /**
     * Format validation summary.
     */
    private function formatValidationSummary(array $validationSummary): array
    {
        return [
            'trial_balance_variance' => [
                'amount' => $validationSummary['trial_balance_variance'] ?? 0,
                'is_balanced' => ($validationSummary['trial_balance_variance'] ?? 0) == 0,
            ],
            'unposted_documents' => $validationSummary['unposted_documents'] ?? [],
            'warnings' => $validationSummary['warnings'] ?? [],
            'has_blocking_issues' => ! empty($validationSummary['unposted_documents']),
            'blocking_count' => collect($validationSummary['unposted_documents'] ?? [])->where('blocking', true)->count(),
        ];
    }

    /**
     * Get available workflow actions based on status and permissions.
     */
    private function getAvailableActions(User $user, string $status, array $taskStats): array
    {
        $actions = [];

        switch ($status) {
            case 'not_started':
                if ($user->can('period-close.start')) {
                    $actions[] = [
                        'action' => 'start',
                        'label' => 'Start Period Close',
                        'description' => 'Initiate the monthly closing process',
                        'primary' => true,
                    ];
                }
                break;

            case 'in_review':
                if ($user->can('period-close.validate')) {
                    $actions[] = [
                        'action' => 'validate',
                        'label' => 'Run Validations',
                        'description' => 'Check for unposted documents and trial balance issues',
                    ];
                }
                if ($taskStats['required_completed'] === $taskStats['required_total'] && $user->can('period-close.lock')) {
                    $actions[] = [
                        'action' => 'submit_for_approval',
                        'label' => 'Submit for Approval',
                        'description' => 'All required tasks completed, ready for review',
                        'primary' => true,
                    ];
                }
                break;

            case 'awaiting_approval':
                if ($user->can('period-close.lock')) {
                    $actions[] = [
                        'action' => 'lock',
                        'label' => 'Lock Period',
                        'description' => 'Lock the period to prevent further modifications',
                        'primary' => true,
                        'warning' => 'This will prevent further modifications to the period',
                    ];
                }
                if ($user->can('period-close.validate')) {
                    $actions[] = [
                        'action' => 'validate',
                        'label' => 'Re-run Validations',
                        'description' => 'Check for any new issues',
                    ];
                }
                break;

            case 'locked':
                if ($user->can('period-close.complete')) {
                    $actions[] = [
                        'action' => 'complete',
                        'label' => 'Complete Close',
                        'description' => 'Finalize the period close process',
                        'primary' => true,
                        'warning' => 'This will permanently close the period',
                    ];
                }
                break;

            case 'closed':
                if ($user->can('period-close.reopen')) {
                    $actions[] = [
                        'action' => 'reopen',
                        'label' => 'Reopen Period',
                        'description' => 'Reopen the period for modifications',
                        'warning' => 'This requires justification and will be audited',
                    ];
                }
                if ($user->can('period-close.reports.view')) {
                    $actions[] = [
                        'action' => 'generate_reports',
                        'label' => 'Generate Reports',
                        'description' => 'Create financial statements for the closed period',
                    ];
                }
                break;
        }

        // Add adjustment action for appropriate statuses
        if (in_array($status, ['in_review', 'awaiting_approval']) && $user->can('period-close.adjust')) {
            $actions[] = [
                'action' => 'create_adjustment',
                'label' => 'Create Adjusting Entry',
                'description' => 'Create a period-end adjusting journal entry',
            ];
        }

        return $actions;
    }

    /**
     * Get recent activity for the period close.
     */
    private function getRecentActivity(PeriodClose $periodClose): array
    {
        $activities = [];

        // Started activity
        if ($periodClose->started_at && $periodClose->started_by) {
            $activities[] = [
                'type' => 'started',
                'description' => 'Period close started',
                'user_id' => $periodClose->started_by,
                'user_name' => $periodClose->starter?->name,
                'timestamp' => $periodClose->started_at->toISOString(),
            ];
        }

        // Recent task completions (last 5)
        $recentTasks = $periodClose->tasks()
            ->whereNotNull('completed_at')
            ->where('completed_by', '!=', null)
            ->orderBy('completed_at', 'desc')
            ->limit(5)
            ->get();

        foreach ($recentTasks as $task) {
            $activities[] = [
                'type' => 'task_completed',
                'description' => "Task '{$task->title}' completed",
                'user_id' => $task->completed_by,
                'user_name' => $task->completer?->name,
                'timestamp' => $task->completed_at->toISOString(),
                'task_code' => $task->code,
            ];
        }

        return $activities;
    }
}
