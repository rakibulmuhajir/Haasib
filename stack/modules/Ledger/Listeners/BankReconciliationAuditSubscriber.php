<?php

namespace Modules\Ledger\Listeners;

use App\Models\BankReconciliation;
use App\Models\BankReconciliationMatch;
use Illuminate\Support\Facades\Auth;
use Modules\Ledger\Events\BankReconciliationAdjustmentCreated;
use Modules\Ledger\Events\BankReconciliationMatched;
use Modules\Ledger\Events\BankReconciliationStatusChanged;

class BankReconciliationAuditSubscriber
{
    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe($events)
    {
        $events->listen(
            BankReconciliationStatusChanged::class,
            [BankReconciliationAuditSubscriber::class, 'handleStatusChanged']
        );

        $events->listen(
            BankReconciliationMatched::class,
            [BankReconciliationAuditSubscriber::class, 'handleMatchCreated']
        );

        $events->listen(
            BankReconciliationAdjustmentCreated::class,
            [BankReconciliationAuditSubscriber::class, 'handleAdjustmentCreated']
        );
    }

    /**
     * Handle reconciliation status change events.
     */
    public function handleStatusChanged(BankReconciliationStatusChanged $event)
    {
        $reconciliation = $event->reconciliation;
        $user = $event->user;

        // Create audit log entry
        activity()
            ->performedOn($reconciliation)
            ->causedBy($user)
            ->withProperties([
                'action' => 'status_change',
                'old_status' => $event->oldStatus,
                'new_status' => $event->newStatus,
                'reconciliation_id' => $reconciliation->id,
                'statement_id' => $reconciliation->statement_id,
                'ledger_account_id' => $reconciliation->ledger_account_id,
                'company_id' => $reconciliation->company_id,
                'user_id' => $user->id,
                'timestamp' => now()->toISOString(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'variance_before' => $reconciliation->variance,
                'variance_after' => $reconciliation->variance, // Should be same for status changes
            ])
            ->log("Bank reconciliation status changed from {$event->oldStatus} to {$event->newStatus}");

        // Log additional context based on status
        switch ($event->newStatus) {
            case 'completed':
                $this->logCompletionContext($reconciliation, $user);
                break;

            case 'locked':
                $this->logLockingContext($reconciliation, $user);
                break;

            case 'reopened':
                $this->logReopeningContext($reconciliation, $user);
                break;
        }
    }

    /**
     * Handle match created events.
     */
    public function handleMatchCreated(BankReconciliationMatched $event)
    {
        $match = $event->match;
        $user = $event->user;

        activity()
            ->performedOn($match->reconciliation)
            ->causedBy($user)
            ->withProperties([
                'action' => 'match_created',
                'match_id' => $match->id,
                'statement_line_id' => $match->statement_line_id,
                'source_type' => $match->source_type,
                'source_id' => $match->source_id,
                'amount' => $match->amount,
                'confidence_score' => $match->confidence_score,
                'auto_matched' => $match->auto_matched,
                'reconciliation_id' => $match->reconciliation_id,
                'company_id' => $match->reconciliation->company_id,
                'user_id' => $user->id,
                'timestamp' => now()->toISOString(),
                'ip_address' => request()->ip(),
                'variance_impact' => $this->calculateVarianceImpact($match),
            ])
            ->log(sprintf(
                'Bank reconciliation match created: %s %s for %s (%s)',
                $match->auto_matched ? 'Auto-matched' : 'Manually matched',
                number_format(abs($match->amount), 2),
                $match->source_type,
                $match->auto_matched ? 'confidence: '.$match->confidence_score : 'user action'
            ));
    }

    /**
     * Handle adjustment created events.
     */
    public function handleAdjustmentCreated(BankReconciliationAdjustmentCreated $event)
    {
        $adjustment = $event->adjustment;
        $user = $event->user;

        activity()
            ->performedOn($adjustment->reconciliation)
            ->causedBy($user)
            ->withProperties([
                'action' => 'adjustment_created',
                'adjustment_id' => $adjustment->id,
                'adjustment_type' => $adjustment->adjustment_type,
                'amount' => $adjustment->amount,
                'description' => $adjustment->description,
                'reconciliation_id' => $adjustment->reconciliation_id,
                'company_id' => $adjustment->reconciliation->company_id,
                'user_id' => $user->id,
                'timestamp' => now()->toISOString(),
                'ip_address' => request()->ip(),
                'variance_impact' => $adjustment->amount,
                'journal_entry_id' => $adjustment->journal_entry_id, // If journal entry was created
            ])
            ->log(sprintf(
                'Bank reconciliation adjustment created: %s for %s (%s)',
                $adjustment->adjustment_type,
                number_format(abs($adjustment->amount), 2),
                $adjustment->description
            ));
    }

    /**
     * Log additional context for reconciliation completion.
     */
    private function logCompletionContext(BankReconciliation $reconciliation, $user)
    {
        $summary = $reconciliation->getSummaryStats();

        activity()
            ->performedOn($reconciliation)
            ->causedBy($user)
            ->withProperties([
                'action' => 'completion_summary',
                'total_matches' => $summary['statement_lines']['matched'],
                'total_adjustments' => $summary['adjustments']['total'],
                'final_variance' => $reconciliation->variance,
                'percent_complete' => $summary['statement_lines']['percentage_matched'],
                'active_duration' => $reconciliation->active_duration,
                'statement_period' => $reconciliation->statement->statement_period,
                'bank_account' => $reconciliation->ledgerAccount->name,
            ])
            ->log('Bank reconciliation completed successfully');
    }

    /**
     * Log additional context for reconciliation locking.
     */
    private function logLockingContext(BankReconciliation $reconciliation, $user)
    {
        activity()
            ->performedOn($reconciliation)
            ->causedBy($user)
            ->withProperties([
                'action' => 'locking_summary',
                'completed_at' => $reconciliation->completed_at?->toISOString(),
                'locked_at' => now()->toISOString(),
                'completion_duration' => $reconciliation->completed_at?->diffInMinutes($reconciliation->started_at),
                'final_variance' => $reconciliation->variance,
                'total_matches' => $reconciliation->matches()->count(),
                'total_adjustments' => $reconciliation->adjustments()->count(),
            ])
            ->log('Bank reconciliation locked - no further edits permitted');
    }

    /**
     * Log additional context for reconciliation reopening.
     */
    private function logReopeningContext(BankReconciliation $reconciliation, $user)
    {
        activity()
            ->performedOn($reconciliation)
            ->causedBy($user)
            ->withProperties([
                'action' => 'reopening_summary',
                'previously_locked_at' => $reconciliation->locked_at?->toISOString(),
                'reopened_at' => now()->toISOString(),
                'reopening_reason' => $this->extractReopeningReason($reconciliation->notes),
                'time_since_locking' => $reconciliation->locked_at?->diffInHours(now()),
                'original_completer' => $reconciliation->completedBy?->name,
                'reopener' => $user->name,
            ])
            ->log('Bank reconciliation reopened - edits now permitted');
    }

    /**
     * Calculate the variance impact of a match.
     */
    private function calculateVarianceImpact(BankReconciliationMatch $match): float
    {
        // This would calculate how much this match affects the overall variance
        // For now, return the match amount as the impact
        return $match->amount;
    }

    /**
     * Extract reopening reason from reconciliation notes.
     */
    private function extractReopeningReason(?string $notes): ?string
    {
        if (! $notes) {
            return null;
        }

        // Look for "Reopened: " or "Reopened at " patterns in notes
        if (preg_match('/Reopened:\s*(.+?)(?:\n\n|$)/s', $notes, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Log failed reconciliation actions.
     */
    public function logFailedAction(BankReconciliation $reconciliation, string $action, \Exception $exception)
    {
        $user = Auth::user();

        activity()
            ->performedOn($reconciliation)
            ->causedBy($user)
            ->withProperties([
                'action' => 'failed_action',
                'failed_action_type' => $action,
                'error_message' => $exception->getMessage(),
                'error_code' => $exception->getCode(),
                'reconciliation_id' => $reconciliation->id,
                'company_id' => $reconciliation->company_id,
                'user_id' => $user?->id,
                'timestamp' => now()->toISOString(),
                'ip_address' => request()->ip(),
                'stack_trace' => config('app.debug') ? $exception->getTraceAsString() : null,
            ])
            ->log("Bank reconciliation action failed: {$action} - {$exception->getMessage()}");
    }

    /**
     * Log access to reconciliation reports.
     */
    public function logReportAccess(BankReconciliation $reconciliation, string $reportType, $user = null)
    {
        $user = $user ?: Auth::user();

        activity()
            ->performedOn($reconciliation)
            ->causedBy($user)
            ->withProperties([
                'action' => 'report_accessed',
                'report_type' => $reportType,
                'reconciliation_id' => $reconciliation->id,
                'company_id' => $reconciliation->company_id,
                'user_id' => $user?->id,
                'timestamp' => now()->toISOString(),
                'ip_address' => request()->ip(),
                'statement_period' => $reconciliation->statement->statement_period,
            ])
            ->log("Bank reconciliation report accessed: {$reportType}");
    }

    /**
     * Log bulk operations on reconciliations.
     */
    public function logBulkOperation(string $operation, array $reconciliationIds, $user = null)
    {
        $user = $user ?: Auth::user();

        activity()
            ->in('bank_reconciliation_bulk_operations')
            ->causedBy($user)
            ->withProperties([
                'action' => 'bulk_operation',
                'operation_type' => $operation,
                'reconciliation_ids' => $reconciliationIds,
                'count' => count($reconciliationIds),
                'user_id' => $user?->id,
                'timestamp' => now()->toISOString(),
                'ip_address' => request()->ip(),
                'company_id' => $user?->current_company_id,
            ])
            ->log("Bulk reconciliation operation: {$operation} affecting ".count($reconciliationIds).' reconciliations');
    }

    /**
     * Log export operations.
     */
    public function logExportOperation(BankReconciliation $reconciliation, string $format, array $filters = [])
    {
        $user = Auth::user();

        activity()
            ->performedOn($reconciliation)
            ->causedBy($user)
            ->withProperties([
                'action' => 'export_operation',
                'export_format' => $format,
                'filters' => $filters,
                'reconciliation_id' => $reconciliation->id,
                'company_id' => $reconciliation->company_id,
                'user_id' => $user?->id,
                'timestamp' => now()->toISOString(),
                'ip_address' => request()->ip(),
                'file_size' => null, // Will be populated by the service
            ])
            ->log("Bank reconciliation data exported: {$format} format");
    }
}
