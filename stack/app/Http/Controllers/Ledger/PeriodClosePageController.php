<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Models\AccountingPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PeriodClosePageController extends Controller
{
    public function __construct()
    {
        // Apply middleware for period close permissions
        $this->middleware('permission:period-close.view')->only(['index', 'show']);
        $this->middleware('permission:period-close.start')->only(['start']);
        $this->middleware('permission:period-close.validate')->only(['validate']);
    }

    /**
     * Display the period close dashboard.
     */
    public function index(Request $request): InertiaResponse
    {
        $user = Auth::user();
        $companyId = session('current_company_id');

        if (! $companyId || ! $user->companies()->where('company_id', $companyId)->exists()) {
            abort(404, 'Company context not found');
        }

        // Get recent periods with their close status
        $periods = AccountingPeriod::where('company_id', $companyId)
            ->with(['periodClose', 'fiscalYear'])
            ->orderBy('end_date', 'desc')
            ->limit(12)
            ->get()
            ->map(function ($period) use ($user) {
                $periodClose = $period->periodClose;
                $canClose = $period->canBeClosed() && ! $periodClose;

                return [
                    'id' => $period->id,
                    'name' => $period->name ?? "Period ending {$period->end_date->format('M j, Y')}",
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
                        'required_tasks_count' => $periodClose->tasks()->where('is_required', true)->count(),
                        'required_completed_count' => $periodClose->tasks()->where('is_required', true)->where('status', 'completed')->count(),
                    ] : null,
                    'can_close' => $canClose && $user->can('period-close.start'),
                    'is_overdue' => $period->end_date->lt(now()->subDays(10)) && $period->status !== 'closed',
                ];
            });

        // Get statistics
        $statistics = $this->getDashboardStatistics($periods);

        // Get upcoming deadlines
        $deadlines = $this->getUpcomingDeadlines($periods);

        return Inertia::render('Ledger/PeriodClose/Index', [
            'periods' => $periods,
            'statistics' => $statistics,
            'deadlines' => $deadlines,
            'permissions' => [
                'can_view' => $user->can('period-close.view'),
                'can_start' => $user->can('period-close.start'),
                'can_validate' => $user->can('period-close.validate'),
                'can_lock' => $user->can('period-close.lock'),
                'can_complete' => $user->can('period-close.complete'),
                'can_reopen' => $user->can('period-close.reopen'),
                'can_adjust' => $user->can('period-close.adjust'),
                'can_manage_tasks' => $user->can('period-close-tasks.update'),
                'can_view_reports' => $user->can('period-close.reports.view'),
            ],
            'current_company' => [
                'id' => $companyId,
                'name' => $user->companies()->where('company_id', $companyId)->first()?->name,
            ],
        ]);
    }

    /**
     * Display a specific period close detail page.
     */
    public function show(Request $request, string $periodId): InertiaResponse
    {
        $user = Auth::user();
        $period = AccountingPeriod::findOrFail($periodId);

        // Verify user access to this period
        if (! $user->companies()->where('company_id', $period->company_id)->exists()) {
            abort(403, 'Access denied');
        }

        // Check if user has permission to view period close
        if (! $user->can('period-close.view')) {
            abort(403, 'Insufficient permissions');
        }

        $periodClose = $period->periodClose;

        return Inertia::render('Ledger/PeriodClose/Show', [
            'period' => [
                'id' => $period->id,
                'name' => $period->name ?? "Period ending {$period->end_date->format('M j, Y')}",
                'start_date' => $period->start_date->format('Y-m-d'),
                'end_date' => $period->end_date->format('Y-m-d'),
                'status' => $period->status,
                'fiscal_year' => [
                    'id' => $period->fiscalYear->id,
                    'name' => $period->fiscalYear->name,
                    'start_date' => $period->fiscalYear->start_date->format('Y-m-d'),
                    'end_date' => $period->fiscalYear->end_date->format('Y-m-d'),
                ],
                'can_be_closed' => $period->canBeClosed(),
                'duration_days' => $period->start_date->diffInDays($period->end_date) + 1,
            ],
            'period_close' => $periodClose ? [
                'id' => $periodClose->id,
                'status' => $periodClose->status,
                'started_at' => $periodClose->started_at?->toISOString(),
                'started_by' => $periodClose->starter?->name,
                'closing_summary' => $periodClose->closing_summary,
                'tasks_count' => $periodClose->tasks()->count(),
                'completed_tasks_count' => $periodClose->tasks()->where('status', 'completed')->count(),
                'required_tasks_count' => $periodClose->tasks()->where('is_required', true)->count(),
                'required_completed_count' => $periodClose->tasks()->where('is_required', true)->where('status', 'completed')->count(),
                'completion_percentage' => $periodClose->tasks()->count() > 0
                    ? round(($periodClose->tasks()->where('status', 'completed')->count() / $periodClose->tasks()->count()) * 100, 1)
                    : 0,
                'required_completion_percentage' => $periodClose->tasks()->where('is_required', true)->count() > 0
                    ? round(($periodClose->tasks()->where('is_required', true)->where('status', 'completed')->count() / $periodClose->tasks()->where('is_required', true)->count()) * 100, 1)
                    : 0,
            ] : null,
            'permissions' => [
                'can_view' => $user->can('period-close.view'),
                'can_start' => $user->can('period-close.start') && $period->canBeClosed() && ! $periodClose,
                'can_validate' => $user->can('period-close.validate') && $periodClose && in_array($periodClose->status, ['in_review', 'awaiting_approval']),
                'can_lock' => $user->can('period-close.lock') && $periodClose && $periodClose->status === 'awaiting_approval',
                'can_complete' => $user->can('period-close.complete') && $periodClose && $periodClose->status === 'locked',
                'can_reopen' => $user->can('period-close.reopen') && $periodClose && $periodClose->status === 'closed',
                'can_adjust' => $user->can('period-close.adjust') && $periodClose && in_array($periodClose->status, ['in_review', 'awaiting_approval', 'locked']),
                'can_manage_tasks' => $user->can('period-close-tasks.update') && $periodClose,
                'can_view_reports' => $user->can('period-close.reports.view') && $periodClose && $periodClose->status === 'closed',
            ],
        ]);
    }

    /**
     * Display the start period close form.
     */
    public function start(Request $request, string $periodId): InertiaResponse
    {
        $user = Auth::user();
        $period = AccountingPeriod::findOrFail($periodId);

        // Verify user access to this period
        if (! $user->companies()->where('company_id', $period->company_id)->exists()) {
            abort(403, 'Access denied');
        }

        // Check if user has permission to start period close
        if (! $user->can('period-close.start')) {
            abort(403, 'Insufficient permissions');
        }

        // Check if period can be closed
        if (! $period->canBeClosed()) {
            abort(400, 'Period cannot be closed');
        }

        // Check if period close already exists
        if ($period->periodClose) {
            abort(409, 'Period close already in progress');
        }

        return Inertia::render('Ledger/PeriodClose/Start', [
            'period' => [
                'id' => $period->id,
                'name' => $period->name ?? "Period ending {$period->end_date->format('M j, Y')}",
                'start_date' => $period->start_date->format('Y-m-d'),
                'end_date' => $period->end_date->format('Y-m-d'),
                'status' => $period->status,
                'fiscal_year' => [
                    'id' => $period->fiscalYear->id,
                    'name' => $period->fiscalYear->name,
                ],
            ],
            'available_templates' => $this->getAvailableTemplates($period),
            'default_tasks' => $this->getDefaultTasks(),
        ]);
    }

    /**
     * Get dashboard statistics.
     */
    private function getDashboardStatistics($periods): array
    {
        return [
            'total_periods' => $periods->count(),
            'closed_periods' => $periods->where('status', 'closed')->count(),
            'active_closes' => $periods->where('status', 'closing')->count(),
            'open_periods' => $periods->whereIn('status', ['open', 'closing'])->count(),
            'overdue_periods' => $periods->where('is_overdue', true)->count(),
            'periods_with_tasks' => $periods->filter(fn ($p) => $p->periodClose && $p->periodClose->tasks->isNotEmpty())->count(),
            'average_completion_time' => $this->getAverageCompletionTime($periods),
        ];
    }

    /**
     * Get upcoming deadlines.
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
                        'period_name' => $period->name ?? "Period ending {$period->end_date->format('M j, Y')}",
                        'deadline' => $deadline->toISOString(),
                        'days_until_deadline' => now()->diffInDays($deadline),
                        'status' => $period->periodClose ? $period->periodClose->status : 'not_started',
                        'priority' => now()->diffInDays($deadline) <= 3 ? 'high' : 'medium',
                    ];
                }
            }
        }

        return collect($deadlines)->sortBy('days_until_deadline')->values()->toArray();
    }

    /**
     * Get available templates for a period.
     */
    private function getAvailableTemplates(AccountingPeriod $period): array
    {
        // This would query the database for available templates
        // For now, return empty array as placeholder
        return [];
    }

    /**
     * Get default tasks for period close.
     */
    private function getDefaultTasks(): array
    {
        return [
            [
                'code' => 'tb-validate',
                'title' => 'Validate Trial Balance',
                'category' => 'trial_balance',
                'sequence' => 1,
                'is_required' => true,
                'description' => 'Ensure trial balance is balanced and accounts reconcile',
            ],
            [
                'code' => 'subledger-ar',
                'title' => 'Reconcile Accounts Receivable',
                'category' => 'subledger',
                'sequence' => 2,
                'is_required' => true,
                'description' => 'Verify AR aging reports match general ledger',
            ],
            [
                'code' => 'subledger-ap',
                'title' => 'Reconcile Accounts Payable',
                'category' => 'subledger',
                'sequence' => 3,
                'is_required' => true,
                'description' => 'Verify AP aging reports match general ledger',
            ],
            [
                'code' => 'bank-reconcile',
                'title' => 'Bank Reconciliation',
                'category' => 'compliance',
                'sequence' => 4,
                'is_required' => true,
                'description' => 'Reconcile bank statements to cash accounts',
            ],
            [
                'code' => 'management-reports',
                'title' => 'Generate Management Reports',
                'category' => 'reporting',
                'sequence' => 5,
                'is_required' => true,
                'description' => 'Prepare income statement and balance sheet',
            ],
        ];
    }

    /**
     * Calculate average completion time for period closes.
     */
    private function getAverageCompletionTime($periods): ?float
    {
        $completedCloses = $periods->filter(fn ($p) => $p->periodClose &&
            $p->periodClose->status === 'closed' &&
            $p->periodClose->started_at &&
            $p->periodClose->completed_at
        );

        if ($completedCloses->isEmpty()) {
            return null;
        }

        $totalHours = $completedCloses->sum(function ($period) {
            return $period->periodClose->started_at->diffInHours($period->periodClose->completed_at);
        });

        return round($totalHours / $completedCloses->count(), 1);
    }
}
