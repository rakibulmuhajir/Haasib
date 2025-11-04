<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Models\AccountingPeriod;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Modules\Ledger\Domain\PeriodClose\Actions\CompletePeriodCloseAction;
use Modules\Ledger\Domain\PeriodClose\Actions\GeneratePeriodCloseReportsAction;
use Modules\Ledger\Domain\PeriodClose\Actions\GetPeriodCloseSnapshotAction;
use Modules\Ledger\Domain\PeriodClose\Actions\LockPeriodCloseAction;
use Modules\Ledger\Domain\PeriodClose\Actions\ReopenPeriodCloseAction;
use Modules\Ledger\Domain\PeriodClose\Actions\StartPeriodCloseAction;
use Modules\Ledger\Domain\PeriodClose\Actions\ValidatePeriodCloseAction;
use Modules\Ledger\Domain\PeriodClose\Exceptions\PeriodCloseException;
use Modules\Ledger\Services\PeriodCloseService;

class PeriodCloseController extends Controller
{
    public function __construct(
        private StartPeriodCloseAction $startAction,
        private GetPeriodCloseSnapshotAction $snapshotAction,
        private ValidatePeriodCloseAction $validateAction,
        private LockPeriodCloseAction $lockAction,
        private CompletePeriodCloseAction $completeAction,
        private GeneratePeriodCloseReportsAction $generateReportsAction,
        private ReopenPeriodCloseAction $reopenAction,
        private PeriodCloseService $periodCloseService
    ) {
        // Apply middleware for period close permissions
        $this->middleware('permission:period-close.view')->only(['index', 'show']);
        $this->middleware('permission:period-close.start')->only(['start']);
        $this->middleware('permission:period-close.validate')->only(['runValidation']);
        $this->middleware('permission:period-close.lock')->only(['lock']);
        $this->middleware('permission:period-close.complete')->only(['complete']);
        $this->middleware('permission:period-close.reopen')->only(['reopen', 'canReopen', 'getReopenHistory', 'extendReopenWindow']);
        $this->middleware('permission:period-close.reports')->only(['generateReports', 'getReports', 'downloadReport']);
        $this->middleware('permission:period-close-tasks.update')->only(['updateTask', 'completeTask']);
    }

    /**
     * Get period close index (list of periods with their close status).
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $companyId = session('current_company_id');

        if (! $companyId || ! $user->companies()->where('company_id', $companyId)->exists()) {
            return response()->json(['error' => 'Company context not found'], 404);
        }

        $periods = AccountingPeriod::where('company_id', $companyId)
            ->with(['periodClose', 'fiscalYear'])
            ->orderBy('end_date', 'desc')
            ->get()
            ->map(function ($period) use ($user) {
                $periodClose = $period->periodClose;
                $canClose = $period->canBeClosed() && ! $periodClose;

                return [
                    'id' => $period->id,
                    'name' => $period->name ?? "Period ending {$period->end_date->format('Y-m-d')}",
                    'start_date' => $period->start_date->format('Y-m-d'),
                    'end_date' => $period->end_date->format('Y-m-d'),
                    'status' => $period->status,
                    'fiscal_year' => [
                        'id' => $period->fiscalYear->id,
                        'name' => $period->fiscalYear->name,
                    ],
                    'period_close' => $periodClose ? [
                        'id' => $periodClose->id,
                        'status' => $periodClose->status,
                        'started_at' => $periodClose->started_at?->toISOString(),
                        'started_by' => $periodClose->starter?->name,
                        'tasks_count' => $periodClose->tasks()->count(),
                        'completed_tasks_count' => $periodClose->tasks()->where('status', 'completed')->count(),
                    ] : null,
                    'can_close' => $canClose && $user->can('period-close.start'),
                    'actions' => $this->getPeriodActions($period, $user),
                ];
            });

        return response()->json([
            'data' => $periods,
            'meta' => [
                'total' => $periods->count(),
                'current_company_id' => $companyId,
            ],
        ]);
    }

    /**
     * Get detailed period close information for a specific period.
     */
    public function show(Request $request, string $periodId): JsonResponse
    {
        $user = Auth::user();
        $period = AccountingPeriod::findOrFail($periodId);

        // Verify user access to this period
        if (! $user->companies()->where('company_id', $period->company_id)->exists()) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        try {
            $snapshot = $this->snapshotAction->execute($period, $user);

            return response()->json($snapshot);
        } catch (PeriodCloseException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Start a period close workflow.
     */
    public function start(Request $request, string $periodId): JsonResponse
    {
        $user = Auth::user();
        $period = AccountingPeriod::findOrFail($periodId);

        // Verify user access to this period
        if (! $user->companies()->where('company_id', $period->company_id)->exists()) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $periodClose = $this->startAction->execute(
                $period,
                $user,
                $request->input('notes')
            );

            return response()->json([
                'message' => 'Period close started successfully',
                'data' => [
                    'id' => $periodClose->id,
                    'accounting_period_id' => $periodClose->accounting_period_id,
                    'status' => $periodClose->status,
                    'started_at' => $periodClose->started_at->toISOString(),
                    'started_by' => $periodClose->starter?->name,
                    'closing_summary' => $periodClose->closing_summary,
                    'tasks_count' => $periodClose->tasks()->count(),
                    'tasks' => $periodClose->tasks->map(function ($task) {
                        return [
                            'id' => $task->id,
                            'code' => $task->code,
                            'title' => $task->title,
                            'category' => $task->category,
                            'sequence' => $task->sequence,
                            'status' => $task->status,
                            'is_required' => $task->is_required,
                            'notes' => $task->notes,
                        ];
                    }),
                ],
            ], 202);
        } catch (PeriodCloseException $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        }
    }

    /**
     * Run period close validations.
     */
    public function runValidation(Request $request, string $periodId): JsonResponse
    {
        $user = Auth::user();
        $period = AccountingPeriod::findOrFail($periodId);

        // Verify user access to this period
        if (! $user->companies()->where('company_id', $period->company_id)->exists()) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        try {
            $validation = $this->validateAction->execute($period, $user);

            return response()->json([
                'message' => 'Validation completed',
                'data' => $validation,
            ]);
        } catch (PeriodCloseException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Update a period close task status.
     */
    public function updateTask(Request $request, string $periodId, string $taskId): JsonResponse
    {
        $user = Auth::user();
        $period = AccountingPeriod::findOrFail($periodId);

        // Verify user access to this period
        if (! $user->companies()->where('company_id', $period->company_id)->exists()) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        $request->validate([
            'status' => 'required|in:pending,in_progress,completed,blocked,waived',
            'notes' => 'nullable|string|max:2000',
            'attachments' => 'nullable|array',
            'attachments.*' => 'string|max:255',
        ]);

        try {
            if ($request->input('status') === 'completed') {
                $task = $this->periodCloseService->completeTask(
                    $taskId,
                    $user,
                    $request->input('notes'),
                    $request->input('attachments')
                );
            } else {
                // For other status updates, we would need additional action classes
                // For now, return a placeholder response
                return response()->json(['error' => 'Task status update not yet implemented for this status'], 501);
            }

            return response()->json([
                'message' => 'Task updated successfully',
                'data' => [
                    'id' => $task->id,
                    'status' => $task->status,
                    'completed_by' => $task->completed_by,
                    'completed_at' => $task->completed_at?->toISOString(),
                    'notes' => $task->notes,
                    'attachment_manifest' => $task->attachment_manifest,
                ],
            ]);
        } catch (PeriodCloseException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Get available actions for a period.
     */
    public function actions(Request $request, string $periodId): JsonResponse
    {
        $user = Auth::user();
        $period = AccountingPeriod::findOrFail($periodId);

        // Verify user access to this period
        if (! $user->companies()->where('company_id', $period->company_id)->exists()) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        $actions = $this->getPeriodActions($period, $user);

        return response()->json([
            'actions' => $actions,
        ]);
    }

    /**
     * Get period close statistics for the dashboard.
     */
    public function statistics(Request $request): JsonResponse
    {
        $user = Auth::user();
        $companyId = session('current_company_id');

        if (! $companyId || ! $user->companies()->where('company_id', $companyId)->exists()) {
            return response()->json(['error' => 'Company context not found'], 404);
        }

        $periods = AccountingPeriod::where('company_id', $companyId)
            ->with(['periodClose', 'periodClose.tasks'])
            ->orderBy('end_date', 'desc')
            ->limit(12) // Last 12 periods
            ->get();

        $statistics = [
            'total_periods' => $periods->count(),
            'closed_periods' => $periods->where('status', 'closed')->count(),
            'active_closes' => $periods->where('status', 'closing')->count(),
            'open_periods' => $periods->whereIn('status', ['open', 'closing'])->count(),
            'periods_with_tasks' => $periods->filter(fn ($p) => $p->periodClose && $p->periodClose->tasks->isNotEmpty())->count(),
            'recent_activity' => $this->getRecentActivity($periods),
            'upcoming_deadlines' => $this->getUpcomingDeadlines($periods),
        ];

        return response()->json($statistics);
    }

    /**
     * Get available actions for a period based on its status and user permissions.
     */
    private function getPeriodActions(AccountingPeriod $period, User $user): array
    {
        $actions = [];
        $periodClose = $period->periodClose;

        if (! $periodClose && $period->canBeClosed() && $user->can('period-close.start')) {
            $actions[] = [
                'action' => 'start',
                'label' => 'Start Period Close',
                'description' => 'Initiate the monthly closing process',
                'method' => 'POST',
                'endpoint' => "/api/v1/ledger/periods/{$period->id}/close/start",
                'primary' => true,
            ];
        }

        if ($periodClose) {
            switch ($periodClose->status) {
                case 'in_review':
                case 'awaiting_approval':
                    if ($user->can('period-close.validate')) {
                        $actions[] = [
                            'action' => 'validate',
                            'label' => 'Run Validations',
                            'description' => 'Check for unposted documents and issues',
                            'method' => 'POST',
                            'endpoint' => "/api/v1/ledger/periods/{$period->id}/close/validate",
                        ];
                    }
                    break;

                case 'closed':
                    if ($user->can('period-close.reopen')) {
                        $actions[] = [
                            'action' => 'reopen',
                            'label' => 'Reopen Period',
                            'description' => 'Reopen period for modifications',
                            'method' => 'POST',
                            'endpoint' => "/api/v1/ledger/periods/{$period->id}/close/reopen",
                            'warning' => 'This will be audited and requires justification',
                        ];
                    }
                    break;
            }
        }

        return $actions;
    }

    /**
     * Get recent activity across periods.
     */
    private function getRecentActivity($periods): array
    {
        $activity = [];

        foreach ($periods->take(5) as $period) {
            $periodClose = $period->periodClose;
            if (! $periodClose) {
                continue;
            }

            if ($periodClose->started_at) {
                $activity[] = [
                    'type' => 'period_close_started',
                    'description' => "Period close started for {$period->name}",
                    'timestamp' => $periodClose->started_at->toISOString(),
                    'user' => $periodClose->starter?->name,
                    'period_id' => $period->id,
                ];
            }

            // Add recent task completions
            $recentTasks = $periodClose->tasks()
                ->whereNotNull('completed_at')
                ->where('completed_at', '>=', now()->subDays(7))
                ->orderBy('completed_at', 'desc')
                ->limit(3)
                ->get();

            foreach ($recentTasks as $task) {
                $activity[] = [
                    'type' => 'task_completed',
                    'description' => "Task '{$task->title}' completed",
                    'timestamp' => $task->completed_at->toISOString(),
                    'user' => $task->completer?->name,
                    'period_id' => $period->id,
                    'task_code' => $task->code,
                ];
            }
        }

        return collect($activity)->sortByDesc('timestamp')->values()->toArray();
    }

    /**
     * Get upcoming deadlines for period closes.
     */
    private function getUpcomingDeadlines($periods): array
    {
        $deadlines = [];

        foreach ($periods as $period) {
            if ($period->status === 'open' || $period->status === 'closing') {
                // Assuming a 5 business day deadline after period end
                $deadline = $period->end_date->addWeekdays(5);

                if ($deadline > now() && $deadline <= now()->addDays(30)) {
                    $deadlines[] = [
                        'period_id' => $period->id,
                        'period_name' => $period->name ?? "Period ending {$period->end_date->format('Y-m-d')}",
                        'deadline' => $deadline->toISOString(),
                        'days_until_deadline' => now()->diffInDays($deadline),
                        'status' => $period->periodClose ? $period->periodClose->status : 'not_started',
                    ];
                }
            }
        }

        return collect($deadlines)->sortBy('days_until_deadline')->values()->toArray();
    }

    /**
     * Create a period close adjustment.
     */
    public function createAdjustment(Request $request, string $periodId): JsonResponse
    {
        $user = Auth::user();
        $period = AccountingPeriod::findOrFail($periodId);

        // Verify user access to this period
        if (! $user->companies()->where('company_id', $period->company_id)->exists()) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        // Check if user has permission to create adjustments
        if (! $user->can('period-close.adjust')) {
            return response()->json(['error' => 'Insufficient permissions to create adjustments'], 403);
        }

        // Check if period close exists and is in the right status
        $periodClose = $period->periodClose;
        if (! $periodClose) {
            return response()->json(['error' => 'Period close not found'], 404);
        }

        $request->validate([
            'reference' => 'required|string|max:50',
            'description' => 'required|string|max:500',
            'entry_date' => 'nullable|date',
            'lines' => 'required|array|min:1',
            'lines.*.account_id' => 'required|string|exists:acct.accounts,id',
            'lines.*.debit' => 'required|numeric|min:0',
            'lines.*.credit' => 'required|numeric|min:0',
            'lines.*.description' => 'required|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Validate that journal entry lines balance
        $totalDebits = collect($request->lines)->sum('debit');
        $totalCredits = collect($request->lines)->sum('credit');

        if (abs($totalDebits - $totalCredits) > 0.01) {
            return response()->json([
                'error' => 'Journal entry must balance',
                'details' => [
                    'total_debits' => $totalDebits,
                    'total_credits' => $totalCredits,
                    'difference' => abs($totalDebits - $totalCredits),
                ],
            ], 422);
        }

        try {
            $periodCloseService = app(Modules\Ledger\Services\PeriodCloseService::class);

            $adjustmentData = [
                'reference' => $request->reference,
                'description' => $request->description,
                'entry_date' => $request->entry_date,
                'lines' => $request->lines,
                'notes' => $request->notes,
            ];

            $journalEntry = $periodCloseService->createAdjustment(
                $periodClose->id,
                $adjustmentData,
                $user
            );

            return response()->json([
                'message' => 'Adjustment created successfully',
                'data' => [
                    'id' => $journalEntry->id,
                    'reference' => $journalEntry->reference,
                    'description' => $journalEntry->description,
                    'date' => $journalEntry->date,
                    'type' => $journalEntry->type,
                    'total_amount' => collect($journalEntry->lines)->sum('debit') + collect($journalEntry->lines)->sum('credit'),
                    'created_at' => $journalEntry->created_at->toISOString(),
                    'lines' => $journalEntry->lines->map(function ($line) {
                        return [
                            'id' => $line->id,
                            'account_id' => $line->account_id,
                            'account_name' => $line->account->name,
                            'account_code' => $line->account->code,
                            'debit' => $line->debit,
                            'credit' => $line->credit,
                            'description' => $line->description,
                        ];
                    }),
                ],
            ], 201);
        } catch (PeriodCloseException $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Failed to create adjustment'], 500);
        }
    }

    /**
     * Get adjustments for a period close.
     */
    public function getAdjustments(Request $request, string $periodId): JsonResponse
    {
        $user = Auth::user();
        $period = AccountingPeriod::findOrFail($periodId);

        // Verify user access to this period
        if (! $user->companies()->where('company_id', $period->company_id)->exists()) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        // Check if user has permission to view adjustments
        if (! $user->can('period-close.view')) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        $periodClose = $period->periodClose;
        if (! $periodClose) {
            return response()->json(['adjustments' => [], 'summary' => null]);
        }

        try {
            $periodCloseService = app(Modules\Ledger\Services\PeriodCloseService::class);
            $adjustmentSummary = $periodCloseService->getAdjustmentSummary($periodClose->id);

            return response()->json([
                'adjustments' => $adjustmentSummary['adjustments'],
                'summary' => [
                    'total_count' => $adjustmentSummary['total_count'],
                    'total_debits' => $adjustmentSummary['total_debits'],
                    'total_credits' => $adjustmentSummary['total_credits'],
                    'net_impact' => $adjustmentSummary['net_impact'],
                ],
                'period_close' => [
                    'id' => $periodClose->id,
                    'status' => $periodClose->status,
                    'allows_adjustments' => in_array($periodClose->status, ['in_review', 'awaiting_approval', 'locked']),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Failed to retrieve adjustments'], 500);
        }
    }

    /**
     * Delete a period close adjustment.
     */
    public function deleteAdjustment(Request $request, string $periodId, string $journalEntryId): JsonResponse
    {
        $user = Auth::user();
        $period = AccountingPeriod::findOrFail($periodId);

        // Verify user access to this period
        if (! $user->companies()->where('company_id', $period->company_id)->exists()) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        // Check if user has permission to delete adjustments
        if (! $user->can('period-close.adjust')) {
            return response()->json(['error' => 'Insufficient permissions to delete adjustments'], 403);
        }

        $periodClose = $period->periodClose;
        if (! $periodClose) {
            return response()->json(['error' => 'Period close not found'], 404);
        }

        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $periodCloseService = app(Modules\Ledger\Services\PeriodCloseService::class);

            $deleted = $periodCloseService->deleteAdjustment(
                $periodClose->id,
                $journalEntryId,
                $user
            );

            if ($deleted) {
                return response()->json([
                    'message' => 'Adjustment deleted successfully',
                ]);
            } else {
                return response()->json(['error' => 'Failed to delete adjustment'], 500);
            }
        } catch (PeriodCloseException $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Failed to delete adjustment'], 500);
        }
    }

    /**
     * Lock a period close.
     */
    public function lock(Request $request, string $periodId): JsonResponse
    {
        $user = Auth::user();
        $companyId = session('current_company_id');

        if (! $companyId || ! $user->companies()->where('company_id', $companyId)->exists()) {
            return response()->json(['error' => 'Company context not found'], 404);
        }

        $period = AccountingPeriod::where('company_id', $companyId)
            ->where('id', $periodId)
            ->firstOrFail();

        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $periodClose = $period->periodClose ?? throw new PeriodCloseException('Period close not found for this period');

            $context = new \App\Support\ServiceContext(
                userId: $user->id,
                companyId: $companyId,
                requestId: 'lock-period-close-'.$periodId,
                idempotencyKey: 'lock-'.$periodId.'-'.time()
            );

            $result = $this->lockAction->execute($periodClose, [
                'reason' => $request->input('reason'),
            ], $context);

            return response()->json([
                'message' => 'Period close locked successfully',
                'data' => [
                    'id' => $periodClose->id,
                    'status' => $periodClose->fresh()->status,
                    'locked_at' => $periodClose->fresh()->locked_at,
                    'locked_by' => $user->id,
                    'lock_reason' => $request->input('reason'),
                ],
            ]);

        } catch (PeriodCloseException $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['error' => 'Insufficient permissions to lock period close'], 403);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Failed to lock period close'], 500);
        }
    }

    /**
     * Complete a period close.
     */
    public function complete(Request $request, string $periodId): JsonResponse
    {
        $user = Auth::user();
        $companyId = session('current_company_id');

        if (! $companyId || ! $user->companies()->where('company_id', $companyId)->exists()) {
            return response()->json(['error' => 'Company context not found'], 404);
        }

        $period = AccountingPeriod::where('company_id', $companyId)
            ->where('id', $periodId)
            ->firstOrFail();

        $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $periodClose = $period->periodClose ?? throw new PeriodCloseException('Period close not found for this period');

            $context = new \App\Support\ServiceContext(
                userId: $user->id,
                companyId: $companyId,
                requestId: 'complete-period-close-'.$periodId,
                idempotencyKey: 'complete-'.$periodId.'-'.time()
            );

            $result = $this->completeAction->execute($periodClose, [
                'notes' => $request->input('notes'),
            ], $context);

            return response()->json([
                'message' => 'Period close completed successfully',
                'data' => [
                    'id' => $periodClose->id,
                    'status' => $periodClose->fresh()->status,
                    'completed_at' => $periodClose->fresh()->completed_at,
                    'completed_by' => $user->id,
                    'completion_notes' => $request->input('notes'),
                    'accounting_period_status' => $period->fresh()->status,
                ],
            ]);

        } catch (PeriodCloseException $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['error' => 'Insufficient permissions to complete period close'], 403);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Failed to complete period close'], 500);
        }
    }

    /**
     * Check if a period close can be locked.
     */
    public function canLock(Request $request, string $periodId): JsonResponse
    {
        $user = Auth::user();
        $companyId = session('current_company_id');

        if (! $companyId || ! $user->companies()->where('company_id', $companyId)->exists()) {
            return response()->json(['error' => 'Company context not found'], 404);
        }

        $period = AccountingPeriod::where('company_id', $companyId)
            ->where('id', $periodId)
            ->firstOrFail();

        try {
            $periodClose = $period->periodClose ?? throw new PeriodCloseException('Period close not found for this period');

            $canLockInfo = $this->lockAction->canLock($periodClose);

            return response()->json([
                'data' => $canLockInfo,
            ]);

        } catch (PeriodCloseException $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Failed to check lock status'], 500);
        }
    }

    /**
     * Check if a period close can be completed.
     */
    public function canComplete(Request $request, string $periodId): JsonResponse
    {
        $user = Auth::user();
        $companyId = session('current_company_id');

        if (! $companyId || ! $user->companies()->where('company_id', $companyId)->exists()) {
            return response()->json(['error' => 'Company context not found'], 404);
        }

        $period = AccountingPeriod::where('company_id', $companyId)
            ->where('id', $periodId)
            ->firstOrFail();

        try {
            $periodClose = $period->periodClose ?? throw new PeriodCloseException('Period close not found for this period');

            $canCompleteInfo = $this->completeAction->canComplete($periodClose);

            return response()->json([
                'data' => $canCompleteInfo,
            ]);

        } catch (PeriodCloseException $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Failed to check completion status'], 500);
        }
    }

    /**
     * Generate period close reports.
     */
    public function generateReports(Request $request, string $periodId): JsonResponse
    {
        $user = Auth::user();
        $period = AccountingPeriod::findOrFail($periodId);

        // Verify user access to this period
        if (! $user->companies()->where('company_id', $period->company_id)->exists()) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        $request->validate([
            'report_types' => 'required|array|min:1',
            'report_types.*' => 'required|string|in:income_statement,balance_sheet,cash_flow,trial_balance,interim_trial_balance,final_statements,management_reports,tax_reports',
        ]);

        try {
            $reportId = $this->generateReportsAction->execute(
                $period,
                $request->report_types,
                $user
            );

            return response()->json([
                'message' => 'Report generation started',
                'status' => 'processing',
                'report_id' => $reportId,
                'requested_reports' => $request->report_types,
            ], Response::HTTP_ACCEPTED);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['error' => 'Insufficient permissions to generate reports'], Response::HTTP_FORBIDDEN);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Failed to generate reports'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get reports for a period.
     */
    public function getReports(Request $request, string $periodId): JsonResponse
    {
        $user = Auth::user();
        $period = AccountingPeriod::findOrFail($periodId);

        // Verify user access to this period
        if (! $user->companies()->where('company_id', $period->company_id)->exists()) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        try {
            $reports = $this->periodCloseService->getPeriodCloseReports($periodId);
            $status = $this->periodCloseService->getReportStatus($periodId);

            return response()->json([
                'status' => $status['status'] ?? 'not_started',
                'current_report' => $status,
                'reports' => $reports['reports'] ?? [],
                'total_reports' => $reports['total_reports'] ?? 0,
                'available_report_types' => $this->getAvailableReportTypes($period, $user),
            ]);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['error' => 'Insufficient permissions to view reports'], 403);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Failed to retrieve reports'], 500);
        }
    }

    /**
     * Get report generation status.
     */
    public function getReportStatus(Request $request, string $periodId): JsonResponse
    {
        $user = Auth::user();
        $period = AccountingPeriod::findOrFail($periodId);

        // Verify user access to this period
        if (! $user->companies()->where('company_id', $period->company_id)->exists()) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        try {
            $status = $this->periodCloseService->getReportStatus($periodId);

            if (! $status) {
                return response()->json(['error' => 'No report generation found for this period'], 404);
            }

            return response()->json($status);

        } catch (\Throwable $e) {
            return response()->json(['error' => 'Failed to retrieve report status'], 500);
        }
    }

    /**
     * Download a specific report file.
     */
    public function downloadReport(Request $request, string $periodId, string $reportType): Response
    {
        $user = Auth::user();
        $period = AccountingPeriod::findOrFail($periodId);

        // Verify user access to this period
        if (! $user->companies()->where('company_id', $period->company_id)->exists()) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        // Validate report type
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

        if (! in_array($reportType, $validReportTypes)) {
            return response()->json(['error' => 'Invalid report type'], 404);
        }

        try {
            $fileData = $this->periodCloseService->getReportFileContents($periodId, $reportType);

            if (! $fileData) {
                return response()->json(['error' => 'Report file not found'], 404);
            }

            return response($fileData['contents'])
                ->header('Content-Type', $fileData['mime_type'])
                ->header('Content-Disposition', 'attachment; filename="'.$fileData['file_name'].'"')
                ->header('Content-Length', $fileData['file_size'])
                ->header('X-Generated-At', $fileData['generated_at']);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['error' => 'Insufficient permissions to download reports'], 403);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Failed to download report'], 500);
        }
    }

    /**
     * Get available report types for a period.
     */
    public function getReportOptions(Request $request, string $periodId): JsonResponse
    {
        $user = Auth::user();
        $period = AccountingPeriod::findOrFail($periodId);

        // Verify user access to this period
        if (! $user->companies()->where('company_id', $period->company_id)->exists()) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        try {
            $options = $this->generateReportsAction->getReportOptions($period, $user);

            return response()->json($options);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['error' => 'Insufficient permissions to view report options'], 403);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Failed to retrieve report options'], 500);
        }
    }

    /**
     * Delete a report.
     */
    public function deleteReport(Request $request, string $periodId, string $reportId): JsonResponse
    {
        $user = Auth::user();
        $period = AccountingPeriod::findOrFail($periodId);

        // Verify user access to this period
        if (! $user->companies()->where('company_id', $period->company_id)->exists()) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        try {
            $deleted = $this->periodCloseService->deleteReport($periodId, $reportId, $user);

            if ($deleted) {
                return response()->json([
                    'message' => 'Report deleted successfully',
                ]);
            } else {
                return response()->json(['error' => 'Failed to delete report'], 500);
            }

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['error' => 'Insufficient permissions to delete reports'], 403);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Failed to delete report'], 500);
        }
    }

    /**
     * Get available report types for a period.
     */
    private function getAvailableReportTypes(AccountingPeriod $period, User $user): array
    {
        $periodClose = $period->periodClose;
        $isClosedPeriod = $period->status === 'closed';
        $periodCloseStatus = $periodClose?->status ?? 'not_started';

        $reportTypes = [
            'income_statement' => [
                'label' => 'Income Statement',
                'description' => 'Revenue, expenses, and net income',
                'available' => $periodCloseStatus !== 'not_started',
            ],
            'balance_sheet' => [
                'label' => 'Balance Sheet',
                'description' => 'Assets, liabilities, and equity',
                'available' => $periodCloseStatus !== 'not_started',
            ],
            'cash_flow' => [
                'label' => 'Cash Flow Statement',
                'description' => 'Operating, investing, and financing activities',
                'available' => $periodCloseStatus !== 'not_started',
            ],
            'trial_balance' => [
                'label' => 'Trial Balance',
                'description' => 'Account balances and validations',
                'available' => in_array($periodCloseStatus, ['in_review', 'awaiting_approval', 'locked', 'closed']),
            ],
            'interim_trial_balance' => [
                'label' => 'Interim Trial Balance',
                'description' => 'Current trial balance for review',
                'available' => in_array($periodCloseStatus, ['in_review', 'awaiting_approval']),
            ],
            'final_statements' => [
                'label' => 'Final Financial Statements',
                'description' => 'Complete set of audited statements',
                'available' => $isClosedPeriod && $periodCloseStatus === 'closed',
            ],
        ];

        // Add advanced reports for users with elevated permissions
        if ($user->can('period-close.advanced_reports')) {
            $reportTypes['management_reports'] = [
                'label' => 'Management Reports',
                'description' => 'Detailed operational and analytical reports',
                'available' => $periodCloseStatus !== 'not_started',
            ];

            $reportTypes['tax_reports'] = [
                'label' => 'Tax Reports',
                'description' => 'Tax-specific financial data',
                'available' => $isClosedPeriod && $periodCloseStatus === 'closed',
            ];
        }

        return $reportTypes;
    }

    /**
     * Reopen a closed period.
     */
    public function reopen(Request $request, string $periodId): JsonResponse
    {
        $user = Auth::user();
        $period = AccountingPeriod::findOrFail($periodId);

        // Verify user access to this period
        if (! $user->companies()->where('company_id', $period->company_id)->exists()) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        $request->validate([
            'reason' => 'required|string|max:500',
            'reopen_until' => 'required|date|after:today',
            'notes' => 'nullable|string|max:2000',
            'justification' => 'nullable|string|max:1000',
        ]);

        try {
            // Validate that reopen_until is within reasonable limits
            $reopenUntil = \Carbon\Carbon::parse($request->reopen_until);
            $maxDays = $this->getMaxReopenDays($user, $period);

            if ($reopenUntil->diffInDays(now()) > $maxDays) {
                return response()->json([
                    'error' => "Reopen window cannot exceed {$maxDays} days for users with role '{$this->getUserRole($user, $period->company_id)}'",
                ], 422);
            }

            $reopenData = array_merge($request->all(), [
                'company_id' => $period->company_id,
                'requested_by_user_id' => $user->id,
                'request_ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'session_id' => session()->getId(),
            ]);

            $result = $this->reopenAction->execute($period, $reopenData, $user);

            // Refresh the period data
            $period->refresh();
            $periodClose = $period->periodClose->fresh();

            return response()->json([
                'message' => 'Period reopened successfully',
                'data' => [
                    'id' => $periodClose->id,
                    'status' => $periodClose->status,
                    'accounting_period_status' => $period->status,
                    'reopened_by' => $user->id,
                    'reopened_at' => $periodClose->reopened_at->toISOString(),
                    'reopen_reason' => $request->reason,
                    'reopen_until' => $request->reopen_until,
                    'reopen_notes' => $request->notes,
                    'reopen_history' => $this->periodCloseService->getReopenHistory($periodId),
                    'original_close_date' => $periodClose->closed_at?->toDateString(),
                    'reopened_times' => $periodClose->metadata['reopen_metadata']['reopened_times'] ?? 0,
                ],
            ]);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['error' => 'Insufficient permissions to reopen period'], 403);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Failed to reopen period'], 500);
        }
    }

    /**
     * Check if a period can be reopened.
     */
    public function canReopen(Request $request, string $periodId): JsonResponse
    {
        $user = Auth::user();
        $period = AccountingPeriod::findOrFail($periodId);

        // Verify user access to this period
        if (! $user->companies()->where('company_id', $period->company_id)->exists()) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        try {
            $canReopenInfo = $this->periodCloseService->canReopenPeriod($periodId, $user);

            // Add additional context for the UI
            $canReopenInfo['user_role'] = $this->getUserRole($user, $period->company_id);
            $canReopenInfo['max_reopen_days'] = $this->getMaxReopenDays($user, $period);
            $canReopenInfo['reopen_requirements'] = $this->reopenAction->getReopenRequirements($period, $user);

            return response()->json($canReopenInfo);

        } catch (\Throwable $e) {
            return response()->json(['error' => 'Failed to check reopen status'], 500);
        }
    }

    /**
     * Get reopen history for a period.
     */
    public function getReopenHistory(Request $request, string $periodId): JsonResponse
    {
        $user = Auth::user();
        $period = AccountingPeriod::findOrFail($periodId);

        // Verify user access to this period
        if (! $user->companies()->where('company_id', $period->company_id)->exists()) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        try {
            $history = $this->periodCloseService->getReopenHistory($periodId);

            return response()->json($history);

        } catch (\Throwable $e) {
            return response()->json(['error' => 'Failed to retrieve reopen history'], 500);
        }
    }

    /**
     * Extend the reopen window for an already reopened period.
     */
    public function extendReopenWindow(Request $request, string $periodId): JsonResponse
    {
        $user = Auth::user();
        $period = AccountingPeriod::findOrFail($periodId);

        // Verify user access to this period
        if (! $user->companies()->where('company_id', $period->company_id)->exists()) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        $request->validate([
            'new_until' => 'required|date|after:today',
            'reason' => 'required|string|max:500',
        ]);

        try {
            $result = $this->periodCloseService->extendReopenWindow(
                $periodId,
                $request->new_until,
                $request->reason,
                $user
            );

            if ($result) {
                return response()->json([
                    'message' => 'Reopen window extended successfully',
                    'data' => [
                        'new_until' => $request->new_until,
                        'extension_reason' => $request->reason,
                        'extended_by' => $user->id,
                    ],
                ]);
            } else {
                return response()->json(['error' => 'Failed to extend reopen window'], 500);
            }

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['error' => 'Insufficient permissions to extend reopen window'], 403);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Failed to extend reopen window'], 500);
        }
    }

    /**
     * Check if a period's reopen window has expired.
     */
    public function checkReopenWindowExpired(Request $request, string $periodId): JsonResponse
    {
        $user = Auth::user();
        $period = AccountingPeriod::findOrFail($periodId);

        // Verify user access to this period
        if (! $user->companies()->where('company_id', $period->company_id)->exists()) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        try {
            $isExpired = $this->periodCloseService->isReopenWindowExpired($periodId);

            return response()->json([
                'is_expired' => $isExpired,
                'period_status' => $period->status,
                'reopens_at' => $period->periodClose?->reopen_until,
            ]);

        } catch (\Throwable $e) {
            return response()->json(['error' => 'Failed to check reopen window status'], 500);
        }
    }

    /**
     * Get user role in company context.
     */
    private function getUserRole(User $user, string $companyId): string
    {
        $membership = $user->companies()->where('company_id', $companyId)->first();

        return $membership?->pivot->role ?? 'unknown';
    }

    /**
     * Get maximum allowed reopen days based on user role.
     */
    private function getMaxReopenDays(User $user, AccountingPeriod $period): int
    {
        $role = $this->getUserRole($user, $period->company_id);

        $roleLimits = [
            'cfo' => 90,        // CFO can reopen for up to 90 days
            'controller' => 30, // Controller can reopen for up to 30 days
            'accountant' => 7,  // Accountant can reopen for up to 7 days
        ];

        return $roleLimits[$role] ?? 7; // Default to 7 days for unknown roles
    }
}
