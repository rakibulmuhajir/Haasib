<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ledger\CompleteReconciliationRequest;
use App\Http\Requests\Ledger\LockReconciliationRequest;
use App\Http\Requests\Ledger\ReopenReconciliationRequest;
use App\Models\BankReconciliation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Modules\Ledger\Actions\BankReconciliation\CompleteReconciliation;
use Modules\Ledger\Actions\BankReconciliation\LockReconciliation;
use Modules\Ledger\Actions\BankReconciliation\ReopenReconciliation;

class BankReconciliationStatusController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
        $this->middleware('permission:bank_reconciliations.complete')->only(['complete']);
        $this->middleware('permission:bank_reconciliations.lock')->only(['lock']);
        $this->middleware('permission:bank_reconciliations.reopen')->only(['reopen']);
    }

    public function complete(CompleteReconciliationRequest $request, BankReconciliation $reconciliation): JsonResponse
    {
        try {
            $this->authorize('complete', $reconciliation);

            $action = CompleteReconciliation::forReconciliation($reconciliation, Auth::user());
            $result = $action->handle();

            if (! $result) {
                return response()->json([
                    'message' => 'Failed to complete reconciliation',
                ], 500);
            }

            // Refresh reconciliation to get updated data
            $reconciliation->refresh();

            return response()->json([
                'message' => 'Reconciliation completed successfully',
                'reconciliation' => [
                    'id' => $reconciliation->id,
                    'status' => $reconciliation->status,
                    'completed_at' => $reconciliation->completed_at?->toISOString(),
                    'completed_by' => $reconciliation->completedBy?->name,
                    'variance' => $reconciliation->formatted_variance,
                    'can_be_edited' => $reconciliation->canBeEdited(),
                    'can_be_locked' => $reconciliation->canBeLocked(),
                    'can_be_reopened' => $reconciliation->canBeReopened(),
                ],
                'summary' => $action->getCompletionSummary(),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], $e->getCode() === 422 ? 422 : 500);
        }
    }

    public function lock(LockReconciliationRequest $request, BankReconciliation $reconciliation): JsonResponse
    {
        try {
            $this->authorize('lock', $reconciliation);

            $action = LockReconciliation::forReconciliation($reconciliation);
            $result = $action->handle();

            if (! $result) {
                return response()->json([
                    'message' => 'Failed to lock reconciliation',
                ], 500);
            }

            // Refresh reconciliation to get updated data
            $reconciliation->refresh();

            return response()->json([
                'message' => 'Reconciliation locked successfully',
                'reconciliation' => [
                    'id' => $reconciliation->id,
                    'status' => $reconciliation->status,
                    'locked_at' => $reconciliation->locked_at?->toISOString(),
                    'variance' => $reconciliation->formatted_variance,
                    'can_be_edited' => $reconciliation->canBeEdited(),
                    'can_be_completed' => $reconciliation->canBeCompleted(),
                    'can_be_reopened' => $reconciliation->canBeReopened(),
                ],
                'summary' => $action->getLockingSummary(),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], $e->getCode() === 422 ? 422 : 500);
        }
    }

    public function reopen(ReopenReconciliationRequest $request, BankReconciliation $reconciliation): JsonResponse
    {
        try {
            $this->authorize('reopen', $reconciliation);

            $reason = $request->validated()['reason'];
            $action = ReopenReconciliation::forReconciliation($reconciliation, Auth::user(), $reason);
            $result = $action->handle();

            if (! $result) {
                return response()->json([
                    'message' => 'Failed to reopen reconciliation',
                ], 500);
            }

            // Refresh reconciliation to get updated data
            $reconciliation->refresh();

            return response()->json([
                'message' => 'Reconciliation reopened successfully',
                'reconciliation' => [
                    'id' => $reconciliation->id,
                    'status' => $reconciliation->status,
                    'completed_at' => $reconciliation->completed_at?->toISOString(),
                    'locked_at' => $reconciliation->locked_at?->toISOString(),
                    'notes' => $reconciliation->notes,
                    'variance' => $reconciliation->formatted_variance,
                    'can_be_edited' => $reconciliation->canBeEdited(),
                    'can_be_completed' => $reconciliation->canBeCompleted(),
                    'can_be_locked' => $reconciliation->canBeLocked(),
                ],
                'summary' => $action->getReopeningSummary(),
                'requires_approval' => $action->requiresApproval(),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], $e->getCode() === 422 ? 422 : 500);
        }
    }

    /**
     * Get reconciliation status and available actions
     */
    public function status(BankReconciliation $reconciliation): JsonResponse
    {
        try {
            $this->authorize('view', $reconciliation);

            $reconciliation->load(['startedBy', 'completedBy', 'statement', 'ledgerAccount']);

            // Get current variance and stats
            $summaryStats = $reconciliation->getSummaryStats();

            return response()->json([
                'reconciliation' => [
                    'id' => $reconciliation->id,
                    'status' => $reconciliation->status,
                    'started_at' => $reconciliation->started_at?->toISOString(),
                    'started_by' => $reconciliation->startedBy?->name,
                    'completed_at' => $reconciliation->completed_at?->toISOString(),
                    'completed_by' => $reconciliation->completedBy?->name,
                    'locked_at' => $reconciliation->locked_at?->toISOString(),
                    'notes' => $reconciliation->notes,
                    'active_duration' => $reconciliation->active_duration,
                ],
                'statement' => [
                    'id' => $reconciliation->statement->id,
                    'period' => $reconciliation->statement->statement_period,
                    'opening_balance' => $reconciliation->statement->formatted_opening_balance,
                    'closing_balance' => $reconciliation->statement->formatted_closing_balance,
                ],
                'bank_account' => [
                    'id' => $reconciliation->ledgerAccount->id,
                    'name' => $reconciliation->ledgerAccount->name,
                ],
                'variance' => [
                    'amount' => $reconciliation->variance,
                    'formatted' => $reconciliation->formatted_variance,
                    'status' => $reconciliation->variance_status,
                    'is_balanced' => abs($reconciliation->variance) <= 0.01,
                ],
                'progress' => [
                    'percent_complete' => $reconciliation->percent_complete,
                    'completeness' => $summaryStats['completeness']['percentage'],
                    'is_complete' => $summaryStats['completeness']['is_complete'],
                    'remaining_steps' => $summaryStats['completeness']['remaining_steps'],
                ],
                'actions' => [
                    'can_be_edited' => $reconciliation->canBeEdited(),
                    'can_be_completed' => $reconciliation->canBeCompleted(),
                    'can_be_locked' => $reconciliation->canBeLocked(),
                    'can_be_reopened' => $reconciliation->canBeReopened(),
                    'can_start_progress' => $reconciliation->isDraft(),
                    'can_delete' => $reconciliation->isDraft() && auth()->user()->can('delete', $reconciliation),
                ],
                'permissions' => [
                    'can_complete' => auth()->user()->can('complete', $reconciliation),
                    'can_lock' => auth()->user()->can('lock', $reconciliation),
                    'can_reopen' => auth()->user()->can('reopen', $reconciliation),
                    'can_delete' => auth()->user()->can('delete', $reconciliation),
                    'can_edit' => auth()->user()->can('update', $reconciliation),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get reconciliation history/audit trail
     */
    public function history(BankReconciliation $reconciliation): JsonResponse
    {
        try {
            $this->authorize('view', $reconciliation);

            // Get activity log for this reconciliation
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
                    ];
                });

            return response()->json([
                'activities' => $activities,
                'reconciliation_id' => $reconciliation->id,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
