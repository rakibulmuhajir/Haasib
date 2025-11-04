<?php

namespace Modules\Ledger\Services;

use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Modules\Ledger\Domain\PeriodClose\Actions\GetPeriodCloseSnapshotAction;
use Modules\Ledger\Domain\PeriodClose\Actions\StartPeriodCloseAction;
use Modules\Ledger\Domain\PeriodClose\Actions\ValidatePeriodCloseAction;
use Modules\Ledger\Domain\PeriodClose\Models\PeriodClose;
use Modules\Ledger\Domain\PeriodClose\Models\PeriodCloseTask;
use Modules\Ledger\Domain\PeriodClose\Models\PeriodCloseTemplate;

class PeriodCloseService
{
    public function __construct(
        private StartPeriodCloseAction $startAction,
        private GetPeriodCloseSnapshotAction $snapshotAction,
        private ValidatePeriodCloseAction $validateAction
    ) {}

    /**
     * Start a period close workflow.
     */
    public function startPeriodClose(string $periodId, User $user, ?string $notes = null): PeriodClose
    {
        $period = AccountingPeriod::findOrFail($periodId);

        return $this->startAction->execute($period, $user, $notes);
    }

    /**
     * Get period close snapshot.
     */
    public function getPeriodCloseSnapshot(string $periodId, ?User $user = null): array
    {
        $period = AccountingPeriod::findOrFail($periodId);

        return $this->snapshotAction->execute($period, $user);
    }

    /**
     * Run period close validations.
     */
    public function validatePeriodClose(string $periodId, ?User $user = null): array
    {
        $period = AccountingPeriod::findOrFail($periodId);

        return $this->validateAction->execute($period, $user);
    }

    /**
     * Complete a period close task.
     */
    public function completeTask(string $taskId, User $user, ?string $notes = null, ?array $attachments = null): PeriodCloseTask
    {
        $task = PeriodCloseTask::findOrFail($taskId);

        if (! $task->canBeCompleted()) {
            throw new \InvalidArgumentException("Task {$taskId} cannot be completed");
        }

        DB::transaction(function () use ($task, $user, $notes, $attachments) {
            $task->markCompleted($user, $notes, $attachments);

            // Check if all required tasks are completed
            $periodClose = $task->periodClose;
            if ($periodClose->allRequiredTasksCompleted()) {
                $periodClose->submitForApproval();
            }
        });

        return $task->fresh();
    }

    /**
     * Get default template for a company and frequency.
     */
    public function getDefaultTemplate(string $companyId, string $frequency = 'monthly'): ?PeriodCloseTemplate
    {
        return PeriodCloseTemplate::where('company_id', $companyId)
            ->where('frequency', $frequency)
            ->where('is_default', true)
            ->where('active', true)
            ->first();
    }

    /**
     * Sync template tasks to a period close.
     */
    public function syncTemplateTasks(PeriodClose $periodClose, ?PeriodCloseTemplate $template = null): void
    {
        if (! $template) {
            $template = $periodClose->template;
        }

        if (! $template) {
            return;
        }

        DB::transaction(function () use ($periodClose, $template) {
            // Delete existing tasks not from template
            $periodClose->tasks()
                ->whereNull('template_task_id')
                ->delete();

            // Sync tasks from template
            foreach ($template->tasks as $templateTask) {
                PeriodCloseTask::updateOrCreate(
                    [
                        'period_close_id' => $periodClose->id,
                        'template_task_id' => $templateTask->id,
                    ],
                    [
                        'code' => $templateTask->code,
                        'title' => $templateTask->title,
                        'category' => $templateTask->category,
                        'sequence' => $templateTask->sequence,
                        'is_required' => $templateTask->is_required,
                        'notes' => $templateTask->default_notes,
                    ]
                );
            }
        });
    }

    /**
     * Create period adjusting journal entry.
     */
    public function createAdjustingEntry(string $periodId, array $entryData, User $user): \App\Models\JournalEntry
    {
        $period = AccountingPeriod::findOrFail($periodId);

        if ($period->isClosed()) {
            throw new \InvalidArgumentException('Cannot create adjusting entries for closed period');
        }

        // This would delegate to the existing LedgerService
        // For now, return a placeholder
        throw new \Exception('Adjusting entry creation not yet implemented');
    }

    // Legacy methods for backward compatibility

    /**
     * Legacy method for backward compatibility - delegates to new action.
     */
    public function startPeriodCloseLegacy(string $periodId, User $user, ?string $notes = null): PeriodClose
    {
        return $this->startPeriodClose($periodId, $user, $notes);
    }

    /**
     * Legacy method for backward compatibility - delegates to new action.
     */
    public function getPeriodCloseSnapshotLegacy(string $periodId): array
    {
        return $this->getPeriodCloseSnapshot($periodId);
    }

    /**
     * Legacy method for backward compatibility - delegates to new action.
     */
    public function validatePeriodCloseLegacy(string $periodId): array
    {
        return $this->validatePeriodClose($periodId);
    }

    // Private helper methods for internal use

    /**
     * Calculate trial balance variance for a period.
     */
    private function calculateTrialBalanceVariance(string $periodId): float
    {
        // This would typically query a trial balance view or calculate from journal entries
        // For now, return 0 as a placeholder
        return 0.00;
    }

    /**
     * Get unposted documents for a period.
     */
    private function getUnpostedDocuments(string $periodId): array
    {
        $unpostedDocuments = [];

        // Check for unposted invoices
        // Since we don't have accounting_period_id, we check by date range based on period
        $period = $this->getPeriod($periodId);
        $unpostedInvoices = DB::table('acct.invoices')
            ->where('company_id', $period->company_id)
            ->whereBetween('invoice_date', [$period->start_date, $period->end_date])
            ->where('status', '!=', 'paid')
            ->where('status', '!=', 'cancelled')
            ->where('status', '!=', 'void')
            ->count();

        if ($unpostedInvoices > 0) {
            $unpostedDocuments[] = [
                'module' => 'invoicing',
                'count' => $unpostedInvoices,
                'blocking' => true,
                'details' => ['Unpaid invoices within period need attention'],
            ];
        }

        // Check for unposted journal entries
        $unpostedEntries = DB::table('acct.journal_entries')
            ->where('company_id', $period->company_id)
            ->whereBetween('entry_date', [$period->start_date, $period->end_date])
            ->where('status', '!=', 'posted')
            ->count();

        if ($unpostedEntries > 0) {
            $unpostedDocuments[] = [
                'module' => 'ledger',
                'count' => $unpostedEntries,
                'blocking' => true,
                'details' => ['Unposted journal entries need to be posted'],
            ];
        }

        // Check for pending payments
        $pendingPayments = DB::table('acct.payments')
            ->where('company_id', $period->company_id)
            ->whereBetween('payment_date', [$period->start_date, $period->end_date])
            ->where('status', 'pending')
            ->count();

        if ($pendingPayments > 0) {
            $unpostedDocuments[] = [
                'module' => 'payments',
                'count' => $pendingPayments,
                'blocking' => false,
                'details' => ['Pending payments need allocation'],
            ];
        }

        return $unpostedDocuments;
    }

    /**
     * Get validation warnings for a period.
     */
    private function getValidationWarnings(string $periodId): array
    {
        $warnings = [];

        // Add any business logic warnings here
        // For example: large balances, unusual transactions, etc.

        return $warnings;
    }

    /**
     * Create a period close adjustment.
     */
    public function createAdjustment(
        string $periodCloseId,
        array $adjustmentData,
        User $user
    ): \App\Models\JournalEntry {
        $periodClose = PeriodClose::findOrFail($periodCloseId);
        $period = $periodClose->accountingPeriod;

        // Validate period close status
        if (! in_array($periodClose->status, ['in_review', 'awaiting_approval', 'locked'])) {
            throw new \InvalidArgumentException(
                "Cannot create adjustment for period close in status: {$periodClose->status}"
            );
        }

        // Validate accounting period status
        if ($period->status === 'closed') {
            throw new \InvalidArgumentException('Cannot create adjustment for closed period');
        }

        $company = $period->company;
        $context = new \App\Support\ServiceContext(
            userId: $user->id,
            companyId: $company->id,
            requestId: 'period-close-adjustment-'.$periodCloseId,
            idempotencyKey: 'adj-'.$periodCloseId.'-'.time()
        );

        // Create the adjustment using LedgerService
        $ledgerService = app(LedgerService::class);

        $journalEntry = $ledgerService->createPeriodCloseAdjustment(
            $company,
            $adjustmentData['lines'],
            $adjustmentData['description'],
            $adjustmentData['reference'],
            $adjustmentData['entry_date'] ?? now()->toDateString(),
            $context,
            $periodCloseId
        );

        // Link the adjustment to the period close
        $this->linkAdjustmentToPeriodClose($periodClose, $journalEntry, $user);

        return $journalEntry;
    }

    /**
     * Get all adjustments for a period close.
     */
    public function getPeriodCloseAdjustments(string $periodCloseId): \Illuminate\Support\Collection
    {
        $periodClose = PeriodClose::findOrFail($periodCloseId);

        return \App\Models\JournalEntry::where('type', 'period_adjustment')
            ->whereJsonContains('metadata->period_close_id', $periodCloseId)
            ->where('company_id', $periodClose->company_id)
            ->with(['lines.account'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get adjustment summary for a period close.
     */
    public function getAdjustmentSummary(string $periodCloseId): array
    {
        $adjustments = $this->getPeriodCloseAdjustments($periodCloseId);

        return [
            'total_count' => $adjustments->count(),
            'total_debits' => $adjustments->sum(function ($je) {
                return $je->lines->sum('debit');
            }),
            'total_credits' => $adjustments->sum(function ($je) {
                return $je->lines->sum('credit');
            }),
            'net_impact' => $adjustments->sum(function ($je) {
                return $je->lines->sum('debit') - $je->lines->sum('credit');
            }),
            'adjustments' => $adjustments->map(function ($je) {
                return [
                    'id' => $je->id,
                    'reference' => $je->reference,
                    'description' => $je->description,
                    'date' => $je->date,
                    'total_amount' => $je->lines->sum('debit') + $je->lines->sum('credit'),
                    'created_at' => $je->created_at,
                    'created_by' => $je->creator?->name,
                ];
            }),
        ];
    }

    /**
     * Delete a period close adjustment (with proper validation).
     */
    public function deleteAdjustment(string $periodCloseId, string $journalEntryId, User $user): bool
    {
        $periodClose = PeriodClose::findOrFail($periodCloseId);
        $journalEntry = \App\Models\JournalEntry::findOrFail($journalEntryId);

        // Validate ownership and period close association
        if ($journalEntry->company_id !== $periodClose->company_id) {
            throw new \InvalidArgumentException('Adjustment does not belong to this period close');
        }

        if ($journalEntry->type !== 'period_adjustment') {
            throw new \InvalidArgumentException('Journal entry is not a period adjustment');
        }

        if (! isset($journalEntry->metadata['period_close_id']) ||
            $journalEntry->metadata['period_close_id'] !== $periodCloseId) {
            throw new \InvalidArgumentException('Adjustment is not linked to this period close');
        }

        // Validate period close status allows deletion
        if (! in_array($periodClose->status, ['in_review', 'awaiting_approval'])) {
            throw new \InvalidArgumentException(
                'Cannot delete adjustment for period close in status: '.$periodClose->status
            );
        }

        // Validate accounting period is not closed
        if ($periodClose->accountingPeriod->status === 'closed') {
            throw new \InvalidArgumentException('Cannot delete adjustment for closed period');
        }

        $context = new \App\Support\ServiceContext(
            userId: $user->id,
            companyId: $periodClose->company_id,
            requestId: 'period-close-delete-adj-'.$journalEntryId,
            idempotencyKey: 'del-adj-'.$journalEntryId.'-'.time()
        );

        // Void the journal entry (don't hard delete for audit purposes)
        return DB::transaction(function () use ($journalEntry, $user, $context) {
            $journalEntry->status = 'void';
            $journalEntry->voided_by = $user->id;
            $journalEntry->voided_at = now();
            $journalEntry->void_reason = 'Deleted during period close review';
            $journalEntry->save();

            // Log the adjustment deletion
            $journalEntry->auditLog('ledger.period_close_adjustment_deleted', [
                'journal_entry_id' => $journalEntry->id,
                'period_close_id' => $periodCloseId,
                'voided_by' => $user->id,
                'voided_at' => now(),
            ], $context);

            return true;
        });
    }

    /**
     * Link an adjustment to a period close (internal method).
     */
    private function linkAdjustmentToPeriodClose(
        PeriodClose $periodClose,
        \App\Models\JournalEntry $journalEntry,
        User $user
    ): void {
        // Update period close metadata to track adjustments
        $metadata = $periodClose->metadata ?? [];
        $adjustments = $metadata['adjustments'] ?? [];

        $adjustments[] = [
            'journal_entry_id' => $journalEntry->id,
            'reference' => $journalEntry->reference,
            'amount' => $journalEntry->lines->sum('debit') + $journalEntry->lines->sum('credit'),
            'created_at' => $journalEntry->created_at->toISOString(),
            'created_by' => $user->id,
        ];

        $periodClose->metadata = array_merge($metadata, [
            'adjustments' => $adjustments,
            'last_adjustment_at' => now()->toISOString(),
            'total_adjustments' => count($adjustments),
        ]);

        $periodClose->save();

        // Log the linkage
        $periodClose->auditLog('ledger.period_close_adjustment_linked', [
            'period_close_id' => $periodClose->id,
            'journal_entry_id' => $journalEntry->id,
            'linked_by' => $user->id,
            'total_adjustments' => count($adjustments),
        ], new \App\Support\ServiceContext(
            userId: $user->id,
            companyId: $periodClose->company_id,
            requestId: 'link-adjustment-'.$journalEntry->id,
            idempotencyKey: 'link-'.$journalEntry->id.'-'.time()
        ));
    }

    /**
     * Lock a period close.
     */
    public function lockPeriodClose(string $periodCloseId, User $user, string $reason): bool
    {
        $periodClose = PeriodClose::findOrFail($periodCloseId);
        $period = $periodClose->accountingPeriod;

        // Validate user permissions
        if (! $user->can('period-close.lock')) {
            throw new \Illuminate\Auth\Access\AuthorizationException('User does not have permission to lock period close');
        }

        // Validate period close status
        if (! in_array($periodClose->status, ['in_review', 'awaiting_approval'])) {
            throw new \InvalidArgumentException("Cannot lock period close in status: {$periodClose->status}");
        }

        // Validate accounting period status
        if ($period->status !== 'open') {
            throw new \InvalidArgumentException('Cannot lock period close: accounting period is not open');
        }

        // Check if all required tasks are completed
        $incompleteRequiredTasks = $periodClose->tasks()
            ->where('is_required', true)
            ->where('status', '!=', 'completed')
            ->count();

        if ($incompleteRequiredTasks > 0) {
            throw new \InvalidArgumentException("Cannot lock period: {$incompleteRequiredTasks} required tasks not completed");
        }

        $lockStartTime = now();

        return DB::transaction(function () use ($periodClose, $period, $user, $reason, $lockStartTime) {
            // Update period close status
            $periodClose->update([
                'status' => 'locked',
                'locked_at' => $lockStartTime,
                'locked_by' => $user->id,
                'lock_reason' => $reason,
            ]);

            // Update audit trail
            $auditTrail = $periodClose->audit_trail ?? [];
            $auditTrail['lock_events'] = array_merge($auditTrail['lock_events'] ?? [], [[
                'action' => 'locked',
                'user_id' => $user->id,
                'timestamp' => $lockStartTime->toISOString(),
                'reason' => $reason,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]]);
            $periodClose->audit_trail = $auditTrail;

            // Update metadata with lock information
            $lockMetadata = [
                'lock_timestamp' => $lockStartTime->toISOString(),
                'lock_duration_ms' => 0, // Will be updated on completion
                'total_tasks_count' => $periodClose->tasks()->count(),
                'completed_tasks_count' => $periodClose->tasks()->where('status', 'completed')->count(),
                'required_tasks_count' => $periodClose->tasks()->where('is_required', true)->count(),
                'required_completed_count' => $periodClose->tasks()->where('is_required', true)->where('status', 'completed')->count(),
                'validation_score' => $this->calculateValidationScore($periodClose),
                'adjustments_count' => $this->getPeriodCloseAdjustments($periodCloseId)->count(),
                'locking_user_id' => $user->id,
                'locking_user_role' => $this->getUserRole($user, $periodClose->company_id),
                'session_id' => session()->getId(),
            ];

            $metadata = $periodClose->metadata ?? [];
            $metadata['lock_metadata'] = $lockMetadata;
            $periodClose->metadata = $metadata;

            $periodClose->save();

            // Log the lock event
            $periodClose->auditLog('ledger.period_close_locked', [
                'period_close_id' => $periodClose->id,
                'accounting_period_id' => $period->id,
                'lock_reason' => $reason,
                'total_tasks' => $lockMetadata['total_tasks_count'],
                'completed_tasks' => $lockMetadata['completed_tasks_count'],
                'required_tasks' => $lockMetadata['required_tasks_count'],
                'required_completed' => $lockMetadata['required_completed_count'],
                'validation_score' => $lockMetadata['validation_score'],
                'adjustments_count' => $lockMetadata['adjustments_count'],
            ], new \App\Support\ServiceContext(
                userId: $user->id,
                companyId: $periodClose->company_id,
                requestId: 'lock-period-close-'.$periodCloseId,
                idempotencyKey: 'lock-'.$periodCloseId.'-'.time()
            ));

            return true;
        });
    }

    /**
     * Complete a period close.
     */
    public function completePeriodClose(string $periodCloseId, User $user, ?string $notes = null): bool
    {
        $periodClose = PeriodClose::findOrFail($periodCloseId);
        $period = $periodClose->accountingPeriod;

        // Validate user permissions
        if (! $user->can('period-close.complete')) {
            throw new \Illuminate\Auth\Access\AuthorizationException('User does not have permission to complete period close');
        }

        // Validate period close status
        if ($periodClose->status !== 'locked') {
            throw new \InvalidArgumentException('Cannot complete period: period must be locked first');
        }

        // Validate accounting period status
        if ($period->status !== 'open') {
            throw new \InvalidArgumentException('Cannot complete period: accounting period is not open');
        }

        $completionStartTime = now();

        return DB::transaction(function () use ($periodClose, $period, $user, $notes, $completionStartTime) {
            // Update accounting period status
            $period->update(['status' => 'closed']);

            // Update period close status
            $periodClose->update([
                'status' => 'closed',
                'completed_at' => $completionStartTime,
                'completed_by' => $user->id,
                'completion_notes' => $notes,
            ]);

            // Calculate lock duration
            $lockDuration = $completionStartTime->diffInMilliseconds($periodClose->locked_at);

            // Update audit trail
            $auditTrail = $periodClose->audit_trail ?? [];
            $auditTrail['completion_events'] = array_merge($auditTrail['completion_events'] ?? [], [[
                'action' => 'completed',
                'user_id' => $user->id,
                'timestamp' => $completionStartTime->toISOString(),
                'notes' => $notes,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]]);
            $periodClose->audit_trail = $auditTrail;

            // Update metadata with completion information
            $metadata = $periodClose->metadata ?? [];
            if (isset($metadata['lock_metadata'])) {
                $metadata['lock_metadata']['lock_duration_ms'] = $lockDuration;
            }

            $completionMetadata = [
                'completion_timestamp' => $completionStartTime->toISOString(),
                'completion_duration_ms' => $lockDuration,
                'completion_user_id' => $user->id,
                'completion_user_role' => $this->getUserRole($user, $periodClose->company_id),
                'completion_session_id' => session()->getId(),
                'final_task_completion_rate' => $this->calculateTaskCompletionRate($periodClose),
                'final_validation_score' => $this->calculateValidationScore($periodClose),
                'total_adjustments_final' => $this->getPeriodCloseAdjustments($periodCloseId)->count(),
            ];
            $metadata['completion_metadata'] = $completionMetadata;
            $periodClose->metadata = $metadata;

            $periodClose->save();

            // Log the completion event
            $periodClose->auditLog('ledger.period_close_completed', [
                'period_close_id' => $periodClose->id,
                'accounting_period_id' => $period->id,
                'completion_notes' => $notes,
                'completion_duration_ms' => $lockDuration,
                'final_task_completion_rate' => $completionMetadata['final_task_completion_rate'],
                'final_validation_score' => $completionMetadata['final_validation_score'],
                'total_adjustments' => $completionMetadata['total_adjustments_final'],
                'closed_period_status' => $period->status,
            ], new \App\Support\ServiceContext(
                userId: $user->id,
                companyId: $periodClose->company_id,
                requestId: 'complete-period-close-'.$periodCloseId,
                idempotencyKey: 'complete-'.$periodCloseId.'-'.time()
            ));

            // Log the accounting period closure
            $period->auditLog('ledger.accounting_period_closed', [
                'period_id' => $period->id,
                'period_close_id' => $periodClose->id,
                'closed_by' => $user->id,
                'closure_timestamp' => $completionStartTime->toISOString(),
                'days_open' => $period->start_date->diffInDays($period->end_date),
            ], new \App\Support\ServiceContext(
                userId: $user->id,
                companyId: $period->company_id,
                requestId: 'close-accounting-period-'.$period->id,
                idempotencyKey: 'close-period-'.$period->id.'-'.time()
            ));

            return true;
        });
    }

    /**
     * Calculate validation score for a period close.
     */
    private function calculateValidationScore(PeriodClose $periodClose): int
    {
        // Get the most recent validation result
        $validationResults = $this->validatePeriodClose($periodClose->accounting_period_id);

        if (! isset($validationResults['score'])) {
            return 100; // Default to perfect score if no validation data
        }

        return (int) $validationResults['score'];
    }

    /**
     * Calculate task completion rate.
     */
    private function calculateTaskCompletionRate(PeriodClose $periodClose): float
    {
        $totalTasks = $periodClose->tasks()->count();
        if ($totalTasks === 0) {
            return 100.0;
        }

        $completedTasks = $periodClose->tasks()->where('status', 'completed')->count();

        return round(($completedTasks / $totalTasks) * 100, 2);
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
     * Generate period close reports.
     */
    public function generateReports(string $periodId, array $reportTypes, User $user): string
    {
        $period = AccountingPeriod::findOrFail($periodId);
        $periodClose = $period->periodClose;

        if (! $periodClose) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Period close not found for this period');
        }

        // Validate user permissions
        if (! $user->can('period-close.reports')) {
            throw new \Illuminate\Auth\Access\AuthorizationException('User does not have permission to generate reports');
        }

        // Validate report types
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

        // Validate final reports require closed period
        $finalReportTypes = ['final_statements', 'tax_reports'];
        $hasFinalReports = array_intersect($reportTypes, $finalReportTypes);
        if ($hasFinalReports && $period->status !== 'closed') {
            throw new \InvalidArgumentException('Final reports require closed period');
        }

        $reportId = \Str::uuid();
        $generationStartTime = now();

        return DB::transaction(function () use ($periodClose, $reportTypes, $user, $reportId, $generationStartTime) {
            // Create report record
            DB::table('period_close_reports')->insert([
                'id' => $reportId,
                'period_close_id' => $periodClose->id,
                'report_types' => json_encode($reportTypes),
                'status' => 'processing',
                'requested_by' => $user->id,
                'requested_at' => $generationStartTime,
                'metadata' => json_encode([
                    'generation_context' => [
                        'user_id' => $user->id,
                        'company_id' => $periodClose->company_id,
                        'period_close_id' => $periodClose->id,
                        'accounting_period_id' => $periodClose->accounting_period_id,
                        'request_ip' => request()->ip(),
                        'request_user_agent' => request()->userAgent(),
                        'session_id' => session()->getId(),
                    ],
                    'report_configuration' => [
                        'include_attachments' => true,
                        'include_audit_summary' => true,
                        'format' => 'pdf',
                        'locale' => 'en-US',
                    ],
                ]),
                'created_at' => $generationStartTime,
                'updated_at' => $generationStartTime,
            ]);

            // Queue the report generation job
            dispatch(new \Modules\Ledger\Jobs\GeneratePeriodCloseReportsJob(
                $reportId,
                $periodClose->id,
                $reportTypes,
                $user
            ));

            // Log the report request
            $periodClose->auditLog('ledger.period_close.reports.requested', [
                'report_id' => $reportId,
                'report_types' => $reportTypes,
                'requested_by' => $user->id,
                'request_timestamp' => $generationStartTime->toISOString(),
            ], new \App\Support\ServiceContext(
                userId: $user->id,
                companyId: $periodClose->company_id,
                requestId: 'generate-reports-'.$reportId,
                idempotencyKey: 'reports-'.$reportId.'-'.time()
            ));

            return $reportId;
        });
    }

    /**
     * Get report generation status.
     */
    public function getReportStatus(string $periodId): ?array
    {
        $period = AccountingPeriod::findOrFail($periodId);
        $periodClose = $period->periodClose;

        if (! $periodClose) {
            return null;
        }

        $report = DB::table('period_close_reports')
            ->where('period_close_id', $periodClose->id)
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $report) {
            return null;
        }

        return [
            'report_id' => $report->id,
            'status' => $report->status,
            'report_types' => json_decode($report->report_types),
            'requested_at' => $report->requested_at,
            'generated_at' => $report->generated_at,
            'error_message' => $report->error_message,
            'file_paths' => $report->file_paths ? json_decode($report->file_paths, true) : null,
            'metadata' => $report->metadata ? json_decode($report->metadata, true) : null,
        ];
    }

    /**
     * Get all reports for a period close.
     */
    public function getPeriodCloseReports(string $periodId): array
    {
        $period = AccountingPeriod::findOrFail($periodId);
        $periodClose = $period->periodClose;

        if (! $periodClose) {
            return [];
        }

        $reports = DB::table('period_close_reports')
            ->where('period_close_id', $periodClose->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($report) {
                return [
                    'id' => $report->id,
                    'status' => $report->status,
                    'report_types' => json_decode($report->report_types),
                    'requested_at' => $report->requested_at,
                    'generated_at' => $report->generated_at,
                    'requested_by' => $report->requested_by,
                    'error_message' => $report->error_message,
                    'file_paths' => $report->file_paths ? json_decode($report->file_paths, true) : [],
                    'metadata' => $report->metadata ? json_decode($report->metadata, true) : [],
                ];
            })
            ->toArray();

        return [
            'period_close_id' => $periodClose->id,
            'reports' => $reports,
            'total_reports' => count($reports),
        ];
    }

    /**
     * Get download URL for a specific report file.
     */
    public function getReportDownloadUrl(string $periodId, string $reportType): ?string
    {
        $period = AccountingPeriod::findOrFail($periodId);
        $periodClose = $period->periodClose;

        if (! $periodClose) {
            return null;
        }

        $report = DB::table('period_close_reports')
            ->where('period_close_id', $periodClose->id)
            ->where('status', 'completed')
            ->whereJsonContains('report_types', $reportType)
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $report || ! $report->file_paths) {
            return null;
        }

        $filePaths = json_decode($report->file_paths, true);
        if (! isset($filePaths[$reportType])) {
            return null;
        }

        return route('period-close.reports.download', [
            'period' => $periodId,
            'reportType' => $reportType,
        ]);
    }

    /**
     * Check if a report file exists and is accessible.
     */
    public function reportFileExists(string $periodId, string $reportType): bool
    {
        $period = AccountingPeriod::findOrFail($periodId);
        $periodClose = $period->periodClose;

        if (! $periodClose) {
            return false;
        }

        $report = DB::table('period_close_reports')
            ->where('period_close_id', $periodClose->id)
            ->where('status', 'completed')
            ->whereJsonContains('report_types', $reportType)
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $report || ! $report->file_paths) {
            return false;
        }

        $filePaths = json_decode($report->file_paths, true);
        $filePath = $filePaths[$reportType] ?? null;

        if (! $filePath) {
            return false;
        }

        return \Storage::disk('local')->exists($filePath);
    }

    /**
     * Get report file contents for download.
     */
    public function getReportFileContents(string $periodId, string $reportType): ?array
    {
        $period = AccountingPeriod::findOrFail($periodId);
        $periodClose = $period->periodClose;

        if (! $periodClose) {
            return null;
        }

        $report = DB::table('period_close_reports')
            ->where('period_close_id', $periodClose->id)
            ->where('status', 'completed')
            ->whereJsonContains('report_types', $reportType)
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $report || ! $report->file_paths) {
            return null;
        }

        $filePaths = json_decode($report->file_paths, true);
        $filePath = $filePaths[$reportType] ?? null;

        if (! $filePath || ! \Storage::disk('local')->exists($filePath)) {
            return null;
        }

        $fileContents = \Storage::disk('local')->get($filePath);
        $mimeType = \Storage::disk('local')->mimeType($filePath);
        $fileName = basename($filePath);

        return [
            'contents' => $fileContents,
            'mime_type' => $mimeType,
            'file_name' => $this->generateReportFileName($reportType, $period, $fileName),
            'file_size' => \Storage::disk('local')->size($filePath),
            'generated_at' => $report->generated_at,
        ];
    }

    /**
     * Generate user-friendly report file name.
     */
    private function generateReportFileName(string $reportType, AccountingPeriod $period, string $originalFileName): string
    {
        $periodName = $period->name ?? $period->start_date->format('Y-m');
        $reportTypeLabel = str_replace('_', ' ', $reportType);
        $reportTypeLabel = ucwords($reportTypeLabel);

        $extension = pathinfo($originalFileName, PATHINFO_EXTENSION);

        return "{$reportTypeLabel}_{$periodName}.{$extension}";
    }

    /**
     * Delete a report (with proper validation).
     */
    public function deleteReport(string $periodId, string $reportId, User $user): bool
    {
        $period = AccountingPeriod::findOrFail($periodId);
        $periodClose = $period->periodClose;

        if (! $periodClose) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Period close not found');
        }

        // Validate user permissions
        if (! $user->can('period-close.manage')) {
            throw new \Illuminate\Auth\Access\AuthorizationException('User does not have permission to delete reports');
        }

        $report = DB::table('period_close_reports')
            ->where('id', $reportId)
            ->where('period_close_id', $periodClose->id)
            ->first();

        if (! $report) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Report not found');
        }

        return DB::transaction(function () use ($report, $user, $periodClose) {
            // Delete associated files
            if ($report->file_paths) {
                $filePaths = json_decode($report->file_paths, true);
                foreach ($filePaths as $reportType => $filePath) {
                    if (\Storage::disk('local')->exists($filePath)) {
                        \Storage::disk('local')->delete($filePath);
                    }
                }
            }

            // Delete the report record
            DB::table('period_close_reports')->where('id', $report->id)->delete();

            // Log the deletion
            $periodClose->auditLog('ledger.period_close.reports.deleted', [
                'report_id' => $report->id,
                'deleted_by' => $user->id,
                'original_report_types' => json_decode($report->report_types),
                'original_requested_at' => $report->requested_at,
            ], new \App\Support\ServiceContext(
                userId: $user->id,
                companyId: $periodClose->company_id,
                requestId: 'delete-report-'.$report->id,
                idempotencyKey: 'delete-report-'.$report->id.'-'.time()
            ));

            return true;
        });
    }

    /**
     * Reopen a closed period.
     */
    public function reopenPeriod(string $periodId, array $reopenData, User $user): bool
    {
        $period = AccountingPeriod::findOrFail($periodId);
        $periodClose = $period->periodClose;

        if (! $periodClose) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Period close not found for this period');
        }

        // Validate user permissions
        if (! $user->can('period-close.reopen')) {
            throw new \Illuminate\Auth\Access\AuthorizationException('User does not have permission to reopen periods');
        }

        // Validate period is closed
        if ($period->status !== 'closed') {
            throw new \InvalidArgumentException('Only closed periods can be reopened');
        }

        if ($periodClose->status !== 'closed') {
            throw new \InvalidArgumentException('Period close is not in closed status');
        }

        // Validate reopen data
        $this->validateReopenData($reopenData);

        // Check if period is already reopened
        if ($period->status === 'reopened') {
            throw new \InvalidArgumentException('Period is already reopened');
        }

        // Check company scoping
        if ($period->company_id !== $reopenData['company_id']) {
            throw new \InvalidArgumentException('Period does not belong to the specified company');
        }

        $reopenTime = now();

        return DB::transaction(function () use ($period, $periodClose, $user, $reopenData, $reopenTime) {
            // Update accounting period status
            $period->update([
                'status' => 'reopened',
                'reopened_by' => $user->id,
                'reopened_at' => $reopenTime,
            ]);

            // Update period close status
            $periodClose->update([
                'status' => 'reopened',
                'reopened_by' => $user->id,
                'reopened_at' => $reopenTime,
                'reopen_reason' => $reopenData['reason'],
                'reopen_until' => $reopenData['reopen_until'],
                'reopen_notes' => $reopenData['notes'] ?? null,
            ]);

            // Update audit trail
            $auditTrail = $periodClose->audit_trail ?? [];
            $auditTrail['reopen_events'] = array_merge($auditTrail['reopen_events'] ?? [], [[
                'action' => 'reopened',
                'user_id' => $user->id,
                'timestamp' => $reopenTime->toISOString(),
                'reason' => $reopenData['reason'],
                'reopen_until' => $reopenData['reopen_until'],
                'notes' => $reopenData['notes'] ?? null,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]]);
            $periodClose->audit_trail = $auditTrail;

            // Update metadata with reopen information
            $metadata = $periodClose->metadata ?? [];
            $reopenMetadata = [
                'reopen_timestamp' => $reopenTime->toISOString(),
                'reopen_duration_days' => now()->diffInDays($reopenData['reopen_until']),
                'reopen_reason' => $reopenData['reason'],
                'reopen_justification' => $reopenData['justification'] ?? null,
                'reopen_user_id' => $user->id,
                'reopen_user_role' => $this->getUserRole($user, $periodClose->company_id),
                'session_id' => session()->getId(),
                'original_close_date' => $periodClose->closed_at->toDateString(),
                'reopened_times' => ($metadata['reopen_metadata']['reopened_times'] ?? 0) + 1,
            ];
            $metadata['reopen_metadata'] = $reopenMetadata;
            $periodClose->metadata = $metadata;

            $periodClose->save();

            // Log the reopen event
            $periodClose->auditLog('ledger.period_close_reopened', [
                'period_close_id' => $periodClose->id,
                'accounting_period_id' => $period->id,
                'reopen_reason' => $reopenData['reason'],
                'reopen_until' => $reopenData['reopen_until'],
                'reopen_notes' => $reopenData['notes'] ?? null,
                'original_close_date' => $periodClose->closed_at->toDateString(),
                'reopened_times' => $reopenMetadata['reopened_times'],
            ], new \App\Support\ServiceContext(
                userId: $user->id,
                companyId: $periodClose->company_id,
                requestId: 'reopen-period-close-'.$periodClose->id,
                idempotencyKey: 'reopen-'.$periodClose->id.'-'.time()
            ));

            // Log the accounting period reopening
            $period->auditLog('ledger.accounting_period_reopened', [
                'period_id' => $period->id,
                'period_close_id' => $periodClose->id,
                'reopened_by' => $user->id,
                'reopen_timestamp' => $reopenTime->toISOString(),
                'reopen_reason' => $reopenData['reason'],
                'reopen_until' => $reopenData['reopen_until'],
            ], new \App\Support\ServiceContext(
                userId: $user->id,
                companyId: $period->company_id,
                requestId: 'reopen-accounting-period-'.$period->id,
                idempotencyKey: 'reopen-period-'.$period->id.'-'.time()
            ));

            // Dispatch events for notifications
            event(new \Modules\Ledger\Events\PeriodCloseReopened($periodClose, $user, $reopenData));

            return true;
        });
    }

    /**
     * Check if a period can be reopened.
     */
    public function canReopenPeriod(string $periodId, User $user): array
    {
        $period = AccountingPeriod::findOrFail($periodId);
        $periodClose = $period->periodClose;

        if (! $periodClose) {
            return [
                'can_reopen' => false,
                'reason' => 'Period close not found',
                'requirements' => [],
            ];
        }

        $requirements = [];
        $canReopen = true;
        $reasons = [];

        // Check period status
        if ($period->status !== 'closed') {
            $canReopen = false;
            $reasons[] = 'Period is not closed (current status: '.$period->status.')';
        }

        // Check period close status
        if ($periodClose->status !== 'closed') {
            $canReopen = false;
            $reasons[] = 'Period close is not in closed status (current status: '.$periodClose->status.')';
        }

        // Check user permissions
        if (! $user->can('period-close.reopen')) {
            $canReopen = false;
            $reasons[] = 'User does not have reopen permission';
        }

        // Check company scoping
        if (! $user->companies()->where('company_id', $period->company_id)->exists()) {
            $canReopen = false;
            $reasons[] = 'User does not have access to this company';
        }

        // Check if already reopened
        if ($period->status === 'reopened') {
            $canReopen = false;
            $reasons[] = 'Period is already reopened';
        }

        // Check time since close (optional business rule)
        if ($periodClose->closed_at && $periodClose->closed_at->diffInDays(now()) > 365) {
            $requirements[] = 'Period closed more than 1 year ago - may require special approval';
        }

        // Check number of previous reopens
        $reopenCount = $periodClose->metadata['reopen_metadata']['reopened_times'] ?? 0;
        if ($reopenCount >= 3) {
            $requirements[] = 'Period has been reopened '.$reopenCount.' times - requires senior management approval';
        }

        return [
            'can_reopen' => $canReopen,
            'reason' => $canReopen ? 'Period can be reopened' : implode('; ', $reasons),
            'requirements' => $requirements,
            'period_info' => [
                'period_name' => $period->name,
                'closed_date' => $periodClose->closed_at?->toDateString(),
                'closed_by' => $periodClose->closed_by,
                'previous_reopens' => $reopenCount,
                'days_since_close' => $periodClose->closed_at ? $periodClose->closed_at->diffInDays(now()) : null,
            ],
        ];
    }

    /**
     * Get reopen history for a period.
     */
    public function getReopenHistory(string $periodId): array
    {
        $period = AccountingPeriod::findOrFail($periodId);
        $periodClose = $period->periodClose;

        if (! $periodClose) {
            return [];
        }

        $auditTrail = $periodClose->audit_trail ?? [];
        $reopenEvents = $auditTrail['reopen_events'] ?? [];

        return [
            'period_info' => [
                'id' => $period->id,
                'name' => $period->name,
                'original_close_date' => $periodClose->closed_at?->toDateString(),
                'current_status' => $period->status,
                'period_close_status' => $periodClose->status,
            ],
            'reopen_events' => collect($reopenEvents)->map(function ($event) {
                return [
                    'timestamp' => $event['timestamp'],
                    'user_id' => $event['user_id'],
                    'reason' => $event['reason'],
                    'reopen_until' => $event['reopen_until'],
                    'notes' => $event['notes'] ?? null,
                    'ip_address' => $event['ip_address'] ?? null,
                ];
            })->toArray(),
            'total_reopens' => count($reopenEvents),
            'metadata' => $periodClose->metadata['reopen_metadata'] ?? [],
        ];
    }

    /**
     * Validate reopen data.
     */
    private function validateReopenData(array $data): void
    {
        // Required fields
        if (! isset($data['reason']) || empty(trim($data['reason']))) {
            throw new \InvalidArgumentException('Reopen reason is required');
        }

        if (! isset($data['reopen_until']) || empty($data['reopen_until'])) {
            throw new \InvalidArgumentException('Reopen until date is required');
        }

        // Validate reopen_until date
        try {
            $reopenUntil = \Carbon\Carbon::parse($data['reopen_until']);
            if ($reopenUntil->isPast()) {
                throw new \InvalidArgumentException('Reopen until date must be in the future');
            }

            // Limit maximum reopen window to 90 days
            if ($reopenUntil->diffInDays(now()) > 90) {
                throw new \InvalidArgumentException('Reopen window cannot exceed 90 days');
            }
        } catch (\Carbon\Exceptions\InvalidFormatException $e) {
            throw new \InvalidArgumentException('Invalid date format for reopen_until');
        }

        // Validate reason length
        if (strlen($data['reason']) > 500) {
            throw new \InvalidArgumentException('Reopen reason cannot exceed 500 characters');
        }

        // Optional notes validation
        if (isset($data['notes']) && strlen($data['notes']) > 2000) {
            throw new \InvalidArgumentException('Reopen notes cannot exceed 2000 characters');
        }

        // Optional justification validation
        if (isset($data['justification']) && strlen($data['justification']) > 1000) {
            throw new \InvalidArgumentException('Reopen justification cannot exceed 1000 characters');
        }
    }

    /**
     * Check if a period's reopen window has expired.
     */
    public function isReopenWindowExpired(string $periodId): bool
    {
        $period = AccountingPeriod::findOrFail($periodId);

        if ($period->status !== 'reopened') {
            return false;
        }

        $periodClose = $period->periodClose;
        if (! $periodClose || ! $periodClose->reopen_until) {
            return false;
        }

        return now()->isAfter(\Carbon\Carbon::parse($periodClose->reopen_until));
    }

    /**
     * Extend reopen window for an already reopened period.
     */
    public function extendReopenWindow(string $periodId, string $newUntil, string $reason, User $user): bool
    {
        $period = AccountingPeriod::findOrFail($periodId);
        $periodClose = $period->periodClose;

        if (! $periodClose) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Period close not found');
        }

        if ($period->status !== 'reopened') {
            throw new \InvalidArgumentException('Period must be reopened to extend the window');
        }

        if (! $user->can('period-close.reopen')) {
            throw new \Illuminate\Auth\Access\AuthorizationException('User does not have permission to extend reopen window');
        }

        // Validate new date
        try {
            $newUntilDate = \Carbon\Carbon::parse($newUntil);
            if ($newUntilDate->isPast()) {
                throw new \InvalidArgumentException('New reopen until date must be in the future');
            }
        } catch (\Carbon\Exceptions\InvalidFormatException $e) {
            throw new \InvalidArgumentException('Invalid date format for new reopen until date');
        }

        return DB::transaction(function () use ($periodClose, $newUntil, $reason, $user) {
            $periodClose->update([
                'reopen_until' => $newUntil,
            ]);

            // Log the extension
            $periodClose->auditLog('ledger.period_close_reopen_extended', [
                'period_close_id' => $periodClose->id,
                'previous_until' => $periodClose->reopen_until,
                'new_until' => $newUntil,
                'extension_reason' => $reason,
                'extended_by' => $user->id,
            ], new \App\Support\ServiceContext(
                userId: $user->id,
                companyId: $periodClose->company_id,
                requestId: 'extend-reopen-'.$periodClose->id,
                idempotencyKey: 'extend-'.$periodClose->id.'-'.time()
            ));

            return true;
        });
    }

    /**
     * Create a new period close template.
     */
    public function createTemplate(array $templateData, User $user): PeriodCloseTemplate
    {
        // Validate user permissions
        if (! $user->can('period-close.templates.manage')) {
            throw new \Illuminate\Auth\Access\AuthorizationException('User does not have permission to manage templates');
        }

        // Validate template data
        $this->validateTemplateData($templateData);

        return DB::transaction(function () use ($templateData, $user) {
            // Handle default template setting
            if ($templateData['is_default'] ?? false) {
                // Unset existing default templates for this company
                PeriodCloseTemplate::where('company_id', $templateData['company_id'])
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            // Create the template
            $template = PeriodCloseTemplate::create([
                'company_id' => $templateData['company_id'],
                'name' => $templateData['name'],
                'frequency' => $templateData['frequency'],
                'description' => $templateData['description'] ?? null,
                'is_default' => $templateData['is_default'] ?? false,
                'active' => $templateData['active'] ?? true,
                'metadata' => [
                    'created_by' => $user->id,
                    'created_by_role' => $this->getUserRole($user, $templateData['company_id']),
                    'task_count' => count($templateData['tasks'] ?? []),
                ],
            ]);

            // Create template tasks
            $this->createTemplateTasks($template->id, $templateData['tasks'] ?? []);

            // Log the template creation
            event(new \Modules\Ledger\Events\PeriodCloseTemplateCreated($template, $user));

            return $template->load('templateTasks');
        });
    }

    /**
     * Update an existing period close template.
     */
    public function updateTemplate(string $templateId, array $updateData, User $user): PeriodCloseTemplate
    {
        $template = PeriodClose::findOrFail($templateId);

        // Verify user can access this template
        if ($template->company_id !== $updateData['company_id']) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Template does not belong to the specified company');
        }

        // Validate user permissions
        if (! $user->can('period-close.templates.manage')) {
            throw new \Illuminate\Auth\Access\AuthorizationException('User does not have permission to manage templates');
        }

        // Validate update data
        $this->validateTemplateUpdateData($updateData, $template);

        return DB::transaction(function () use ($template, $updateData, $user) {
            // Handle default template setting
            if (($updateData['is_default'] ?? false) && ! $template->is_default) {
                // Unset existing default templates for this company
                PeriodCloseTemplate::where('company_id', $template->company_id)
                    ->where('id', '!=', $template->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            // Update the template
            $template->update([
                'name' => $updateData['name'],
                'description' => $updateData['description'] ?? $template->description,
                'is_default' => $updateData['is_default'] ?? $template->is_default,
                'active' => $updateData['active'] ?? $template->active,
                'updated_at' => now(),
            ]);

            // Update metadata
            $metadata = $template->metadata ?? [];
            $metadata['updated_by'] = $user->id;
            $metadata['updated_by_role'] = $this->getUserRole($user, $template->company_id);
            $metadata['updated_at'] = now()->toISOString();
            $template->metadata = $metadata;
            $template->save();

            // Log the template update
            event(new \Modules\Ledger\Events\PeriodCloseTemplateUpdated($template, $user));

            return $template->fresh();
        });
    }

    /**
     * Archive (deactivate) a period close template.
     */
    public function archiveTemplate(string $templateId, User $user): bool
    {
        $template = PeriodCloseTemplate::findOrFail($templateId);

        // Verify user can access this template
        if ($template->company_id !== $user->getCurrentCompanyId()) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Template does not belong to the specified company');
        }

        // Validate user permissions
        if (! $user->can('period-close.templates.manage')) {
            throw new \Illuminate\Auth\Access\AuthorizationException('User does not have permission to manage templates');
        }

        return DB::transaction(function () use ($template, $user) {
            $template->update([
                'active' => false,
                'archived_by' => $user->id,
                'archived_at' => now(),
            ]);

            // Update metadata
            $metadata = $template->metadata ?? [];
            $metadata['archived_by'] = $user->id;
            $metadata['archived_at'] = now()->toISOString();
            $template->metadata = $metadata;
            $template->save();

            // Log the template archival
            event(new \Modules\Ledger\Events\PeriodCloseTemplateArchived($template, $user));

            return true;
        });
    }

    /**
     * Sync template tasks to a period close.
     */
    public function syncTemplateToPeriodClose(string $templateId, string $periodCloseId, User $user): array
    {
        $template = PeriodCloseTemplate::findOrFail($templateId);
        $periodClose = PeriodClose::findOrFail($periodCloseId);

        // Verify user can access both template and period close
        if ($template->company_id !== $periodClose->company_id) {
            throw new \InvalidArgumentException('Template and period close must belong to the same company');
        }

        if ($template->company_id !== $user->getCurrentCompanyId()) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Template does not belong to the specified company');
        }

        // Validate user permissions
        if (! $user->can('period-close.templates.manage')) {
            throw new \Illuminate\Auth\Access\AuthorizationException('User does not have permission to manage templates');
        }

        // Validate period close status
        if ($periodClose->status === 'closed') {
            throw new \InvalidArgumentException('Cannot sync template to closed period close');
        }

        return DB::transaction(function () use ($template, $periodClose, $user) {
            // Delete existing tasks for this period close
            $deletedCount = $periodClose->tasks()->delete();

            // Create new tasks from template
            $syncedTasks = [];
            $sequence = 1;

            foreach ($template->templateTasks()->orderBy('sequence')->get() as $templateTask) {
                $periodCloseTask = $periodClose->tasks()->create([
                    'template_task_id' => $templateTask->id,
                    'code' => $templateTask->code,
                    'title' => $templateTask->title,
                    'category' => $templateTask->category,
                    'sequence' => $sequence++,
                    'status' => 'pending',
                    'is_required' => $templateTask->is_required,
                    'notes' => $templateTask->default_notes,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $syncedTasks[] = [
                    'template_task_id' => $templateTask->id,
                    'period_close_task_id' => $periodCloseTask->id,
                    'code' => $templateTask->code,
                    'title' => $templateTask->title,
                    'category' => $templateTask->category,
                ];
            }

            // Update period close with template reference
            $periodClose->update([
                'template_id' => $template->id,
            ]);

            // Update period close metadata
            $metadata = $periodClose->metadata ?? [];
            $metadata['synced_from_template'] = $template->name;
            $metadata['synced_at'] = now()->toISOString();
            $metadata['synced_by'] = $user->id;
            $periodClose->metadata = $metadata;
            $periodClose->save();

            // Log the sync operation
            event(new \Modules\Ledger\Events\PeriodCloseTemplateSynced($template, $periodClose, $user, $syncedTasks));

            return [
                'synced_tasks_count' => count($syncedTasks),
                'template_id' => $templateId,
                'period_close_id' => $periodCloseId,
                'synced_tasks' => $syncedTasks,
            ];
        });
    }

    /**
     * Get templates for a company.
     */
    public function getTemplates(string $companyId, array $filters, User $user): array
    {
        $query = PeriodCloseTemplate::where('company_id', $companyId)
            ->with(['templateTasks']);

        // Apply filters
        if (isset($filters['active'])) {
            $query->where('active', $filters['active']);
        }

        if (isset($filters['frequency'])) {
            $query->where('frequency', $filters['frequency']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                    ->orWhere('description', 'ILIKE', "%{$search}%");
            });
        }

        $templates = $query->orderBy('created_at', 'desc')->get();

        return [
            'data' => $templates->map(function ($template) {
                return [
                    'id' => $template->id,
                    'name' => $template->name,
                    'description' => $template->description,
                    'frequency' => $template->frequency,
                    'is_default' => $template->is_default,
                    'active' => $template->active,
                    'task_count' => $template->template_tasks_count ?? 0,
                    'created_at' => $template->created_at->toISOString(),
                    'updated_at' => $template->updated_at->toISOString(),
                    'metadata' => $template->metadata,
                ];
            })->toArray(),
            'total' => $templates->count(),
            'active_templates' => $templates->where('active', true)->count(),
        ];
    }

    /**
     * Get template details with tasks.
     */
    public function getTemplate(string $templateId, User $user): PeriodCloseTemplate
    {
        $template = PeriodCloseTemplate::where('id', $templateId)
            ->with(['templateTasks'])
            ->firstOrFail();

        // Verify user can access this template
        if ($template->company_id !== $user->getCurrentCompanyId()) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Template does not belong to the specified company');
        }

        return $template;
    }

    /**
     * Get template statistics for a company.
     */
    public function getTemplateStatistics(string $companyId, User $user): array
    {
        $templates = PeriodCloseTemplate::where('company_id', $companyId);

        return [
            'total_templates' => $templates->count(),
            'active_templates' => $templates->where('active', true)->count(),
            'archived_templates' => $templates->where('active', false)->count(),
            'default_template' => $templates->where('is_default', true)->exists(),
            'monthly_templates' => $templates->where('frequency', 'monthly')->count(),
            'quarterly_templates' => $templates->where('frequency', 'quarterly')->count(),
            'annual_templates' => $templates->where('frequency', 'year')->count(),
            'templates_with_tasks' => $templates->whereHas('templateTasks')->count(),
        ];
    }

    /**
     * Duplicate an existing template.
     */
    public function duplicateTemplate(string $templateId, array $duplicateData, User $user): PeriodCloseTemplate
    {
        $sourceTemplate = PeriodCloseTemplate::where('id', $templateId)
            ->with(['templateTasks'])
            ->firstOrFail();

        // Verify user can access this template
        if ($sourceTemplate->company_id !== $user->getCurrentCompanyId()) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Template does not belong to the specified company');
        }

        // Validate user permissions
        if (! $user->can('period-close.templates.manage')) {
            throw new \Illuminate\Auth\Access\AuthorizationException('User does not have permission to manage templates');
        }

        // Prepare duplicated template data
        $templateData = [
            'company_id' => $sourceTemplate->company_id,
            'name' => $duplicateData['name'] ?? "Copy of {$sourceTemplate->name}",
            'description' => $duplicateData['description'] ?? ($sourceTemplate->description ? "Duplicated from: {$sourceTemplate->description}" : null),
            'frequency' => $sourceTemplate->frequency,
            'is_default' => false, // Duplicated templates are never default
            'active' => true,
            'tasks' => $sourceTemplate->templateTasks->map(function ($task) {
                return [
                    'code' => $task->code,
                    'title' => $task->title,
                    'category' => $task->category,
                    'sequence' => $task->sequence,
                    'is_required' => $task->is_required,
                    'default_notes' => $task->default_notes,
                ];
            })->toArray(),
        ];

        return $this->createTemplate($templateData, $user);
    }

    /**
     * Create template tasks for a template.
     */
    private function createTemplateTasks(string $templateId, array $tasksData): void
    {
        $sequence = 1;

        foreach ($tasksData as $taskData) {
            PeriodCloseTemplateTask::create([
                'template_id' => $templateId,
                'code' => $taskData['code'],
                'title' => $taskData['title'],
                'category' => $taskData['category'],
                'sequence' => $sequence++,
                'is_required' => $taskData['is_required'],
                'default_notes' => $taskData['default_notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Validate template creation data.
     */
    private function validateTemplateData(array $data): void
    {
        // Required fields
        if (! isset($data['name']) || empty(trim($data['name']))) {
            throw new \InvalidArgumentException('Template name is required');
        }

        if (! isset($data['frequency'])) {
            throw new \InvalidArgumentException('Template frequency is required');
        }

        if (! isset($data['company_id'])) {
            throw new \InvalidArgumentException('Company ID is required');
        }

        // Validate frequency
        $validFrequencies = ['monthly', 'quarterly', 'annual'];
        if (! in_array($data['frequency'], $validFrequencies)) {
            throw new \InvalidArgumentException('Invalid template frequency');
        }

        // Validate tasks
        if (! isset($data['tasks']) || ! is_array($data['tasks'])) {
            throw new InvalidArgumentException('Template tasks are required');
        }

        if (empty($data['tasks'])) {
            throw new \InvalidArgumentException('Template must have at least one task');
        }

        $this->validateTemplateTasks($data['tasks']);
    }

    /**
     * Validate template update data.
     */
    private function validateTemplateUpdateData(array $data, PeriodCloseTemplate $template): void
    {
        // Validate name uniqueness within company (excluding current template)
        if (isset($data['name']) && $data['name'] !== $template->name) {
            $exists = PeriodCloseTemplate::where('company_id', $template->company_id)
                ->where('name', $data['name'])
                ->where('id', '!=', $template->id)
                ->exists();

            if ($exists) {
                throw new \InvalidArgumentException('Template name must be unique within company');
            }
        }

        // Validate frequency if provided
        if (isset($data['frequency'])) {
            $validFrequencies = ['monthly', 'quarterly', 'annual'];
            if (! in_array($data['frequency'], $validFrequencies)) {
                throw new \InvalidArgumentException('Invalid template frequency');
            }
        }
    }

    /**
     * Validate template tasks array.
     */
    private function validateTemplateTasks(array $tasks): void
    {
        $sequences = [];
        $codes = [];

        foreach ($tasks as $index => $task) {
            // Check required fields
            if (! isset($task['code']) || empty(trim($task['code']))) {
                throw new \InvalidArgumentException('Task '.($index + 1).': Code is required');
            }

            if (! isset($task['title']) || empty(trim($task['title']))) {
                throw new \InvalidArgumentException('Task '.($index + 1).': Title is required');
            }

            if (! isset($task['category'])) {
                throw new \InvalidArgumentException('Task '.($index + 1).': Category is required');
            }

            if (! isset($task['sequence']) || ! is_int($task['sequence']) || $task['sequence'] < 1) {
                throw new \InvalidArgumentException('Task '.($index + 1).': Sequence must be a positive integer');
            }

            if (! isset($task['is_required'])) {
                $task['is_required'] = true;
            }

            // Validate category
            $validCategories = ['trial_balance', 'subledger', 'compliance', 'reporting', 'misc'];
            if (! in_array($task['category'], $validCategories)) {
                throw new \InvalidArgumentException('Task '.($index + 1).": Invalid category '{$task['category']}'");
            }

            // Check for duplicate sequences
            if (in_array($task['sequence'], $sequences)) {
                throw new \InvalidArgumentException('Task '.($index + 1).': Sequence must be unique within template');
            }
            $sequences[] = $task['sequence'];

            // Check for duplicate codes
            if (in_array($task['code'], $codes)) {
                throw new \InvalidArgumentException('Task '.($index + 1).': Code must be unique within template');
            }
            $codes[] = $task['code'];
        }

        // Sort tasks by sequence to ensure proper ordering
        usort($tasks, function ($a, $b) {
            return $a['sequence'] - $b['sequence'];
        });
    }

    // ========== Metric Hooks & Monitoring Methods ==========

    /**
     * Emit metric for period close operation start
     */
    private function emitPeriodCloseStartMetric(PeriodClose $periodClose, User $user, float $durationMs): void
    {
        $metric = [
            'event' => 'period_close.started',
            'timestamp' => now()->toISOString(),
            'user_id' => $user->id,
            'company_id' => $periodClose->company_id,
            'period_close_id' => $periodClose->id,
            'accounting_period_id' => $periodClose->accounting_period_id,
            'duration_ms' => $durationMs,
            'user_role' => $this->getUserRole($user, $periodClose->company_id),
            'source' => 'api',
            'session_id' => session()->getId(),
        ];

        $this->publishMetric($metric);
        $this->incrementCounter('period_close_started', $periodClose->company_id);
        $this->recordDuration('period_close_start_duration', $durationMs, $periodClose->company_id);
    }

    /**
     * Emit metric for task completion
     */
    private function emitTaskCompletionMetric(PeriodCloseTask $task, User $user, float $durationMs): void
    {
        $metric = [
            'event' => 'period_close.task_completed',
            'timestamp' => now()->toISOString(),
            'user_id' => $user->id,
            'company_id' => $task->company_id,
            'period_close_id' => $task->period_close_id,
            'task_id' => $task->id,
            'task_code' => $task->code,
            'task_category' => $task->category,
            'is_required' => $task->is_required,
            'duration_ms' => $durationMs,
            'user_role' => $this->getUserRole($user, $task->company_id),
            'completion_rate' => $this->calculateTaskCompletionRate($task->periodClose),
        ];

        $this->publishMetric($metric);
        $this->incrementCounter('period_close_task_completed', $task->company_id);
        $this->recordDuration('period_close_task_duration', $durationMs, $task->company_id, [
            'category' => $task->category,
            'required' => $task->is_required ? 'true' : 'false',
        ]);
    }

    /**
     * Emit metric for validation execution
     */
    private function emitValidationMetric(array $validationResults, string $companyId, float $durationMs): void
    {
        $metric = [
            'event' => 'period_close.validation_executed',
            'timestamp' => now()->toISOString(),
            'company_id' => $companyId,
            'validation_score' => $validationResults['score'] ?? null,
            'duration_ms' => $durationMs,
            'issues_found' => count($validationResults['issues'] ?? []),
            'warnings_found' => count($validationResults['warnings'] ?? []),
            'trial_balance_variance' => $validationResults['trial_balance_variance'] ?? 0,
            'unposted_documents' => count($validationResults['unposted_documents'] ?? []),
        ];

        $this->publishMetric($metric);
        $this->recordDuration('period_close_validation_duration', $durationMs, $companyId);

        // Track validation scores
        if (isset($validationResults['score'])) {
            $this->recordGauge('period_close_validation_score', $validationResults['score'], $companyId);
        }
    }

    /**
     * Emit metric for period close lock
     */
    private function emitLockMetric(PeriodClose $periodClose, User $user, string $reason, float $durationMs): void
    {
        $metric = [
            'event' => 'period_close.locked',
            'timestamp' => now()->toISOString(),
            'user_id' => $user->id,
            'company_id' => $periodClose->company_id,
            'period_close_id' => $periodClose->id,
            'lock_reason' => $reason,
            'duration_ms' => $durationMs,
            'user_role' => $this->getUserRole($user, $periodClose->company_id),
            'task_completion_rate' => $this->calculateTaskCompletionRate($periodClose),
            'required_tasks_completed' => $periodClose->tasks()->where('is_required', true)->where('status', 'completed')->count(),
            'total_tasks' => $periodClose->tasks()->count(),
        ];

        $this->publishMetric($metric);
        $this->incrementCounter('period_close_locked', $periodClose->company_id);
        $this->recordDuration('period_close_lock_duration', $durationMs, $periodClose->company_id);
    }

    /**
     * Emit metric for period close completion
     */
    private function emitCompletionMetric(PeriodClose $periodClose, User $user, ?string $notes, float $totalDurationMs): void
    {
        $metric = [
            'event' => 'period_close.completed',
            'timestamp' => now()->toISOString(),
            'user_id' => $user->id,
            'company_id' => $periodClose->company_id,
            'period_close_id' => $periodClose->id,
            'completion_notes' => $notes,
            'total_duration_ms' => $totalDurationMs,
            'lock_duration_ms' => $periodClose->locked_at ? now()->diffInMilliseconds($periodClose->locked_at) : 0,
            'user_role' => $this->getUserRole($user, $periodClose->company_id),
            'final_task_completion_rate' => $this->calculateTaskCompletionRate($periodClose),
            'final_validation_score' => $this->calculateValidationScore($periodClose),
            'total_adjustments' => $this->getPeriodCloseAdjustments($periodClose->id)->count(),
        ];

        $this->publishMetric($metric);
        $this->incrementCounter('period_close_completed', $periodClose->company_id);
        $this->recordDuration('period_close_total_duration', $totalDurationMs, $periodClose->company_id);
        $this->recordGauge('period_close_completion_rate', $this->calculateTaskCompletionRate($periodClose), $periodClose->company_id);
    }

    /**
     * Emit metric for period close reopen
     */
    private function emitReopenMetric(PeriodClose $periodClose, User $user, string $reason, ?int $reopenCount): void
    {
        $metric = [
            'event' => 'period_close.reopened',
            'timestamp' => now()->toISOString(),
            'user_id' => $user->id,
            'company_id' => $periodClose->company_id,
            'period_close_id' => $periodClose->id,
            'reopen_reason' => $reason,
            'user_role' => $this->getUserRole($user, $periodClose->company_id),
            'reopened_times' => $reopenCount ?? 1,
            'days_since_close' => $periodClose->closed_at ? $periodClose->closed_at->diffInDays(now()) : null,
        ];

        $this->publishMetric($metric);
        $this->incrementCounter('period_close_reopened', $periodClose->company_id);
        $this->recordGauge('period_close_reopen_count', $reopenCount ?? 1, $periodClose->company_id);
    }

    /**
     * Emit metric for template creation
     */
    private function emitTemplateCreationMetric(PeriodCloseTemplate $template, User $user, float $durationMs): void
    {
        $metric = [
            'event' => 'period_close_template.created',
            'timestamp' => now()->toISOString(),
            'user_id' => $user->id,
            'company_id' => $template->company_id,
            'template_id' => $template->id,
            'template_name' => $template->name,
            'frequency' => $template->frequency,
            'is_default' => $template->is_default,
            'task_count' => $template->templateTasks()->count(),
            'duration_ms' => $durationMs,
            'user_role' => $this->getUserRole($user, $template->company_id),
        ];

        $this->publishMetric($metric);
        $this->incrementCounter('period_close_template_created', $template->company_id);
        $this->recordDuration('period_close_template_creation_duration', $durationMs, $template->company_id);
        $this->recordGauge('period_close_template_task_count', $template->templateTasks()->count(), $template->company_id);
    }

    /**
     * Emit metric for template sync
     */
    private function emitTemplateSyncMetric(PeriodCloseTemplate $template, PeriodClose $periodClose, User $user, int $syncedTasksCount, float $durationMs): void
    {
        $metric = [
            'event' => 'period_close_template.synced',
            'timestamp' => now()->toISOString(),
            'user_id' => $user->id,
            'company_id' => $template->company_id,
            'template_id' => $template->id,
            'period_close_id' => $periodClose->id,
            'synced_tasks_count' => $syncedTasksCount,
            'duration_ms' => $durationMs,
            'user_role' => $this->getUserRole($user, $template->company_id),
            'template_frequency' => $template->frequency,
        ];

        $this->publishMetric($metric);
        $this->incrementCounter('period_close_template_synced', $template->company_id);
        $this->recordDuration('period_close_template_sync_duration', $durationMs, $template->company_id);
    }

    /**
     * Publish metric to monitoring system
     */
    private function publishMetric(array $metric): void
    {
        try {
            // Log to application log
            Log::channel('metrics')->info('Period Close Metric', $metric);

            // Publish to Redis for real-time monitoring
            if (Redis::isConnected()) {
                Redis::publish('period-close-metrics', json_encode($metric));
            }

            // Update Redis counters and gauges
            if (Redis::isConnected()) {
                $keyPrefix = 'metrics:period-close:company:'.$metric['company_id'];

                // Update daily stats
                $today = now()->format('Y-m-d');
                Redis::hincrby("{$keyPrefix}:daily:{$today}", $metric['event'], 1);

                // Set expiration for daily stats (7 days)
                Redis::expire("{$keyPrefix}:daily:{$today}", 7 * 24 * 60 * 60);
            }

            // Cache for dashboard
            $cacheKey = "company_metrics:{$metric['company_id']}:recent";
            $recentMetrics = Cache::get($cacheKey, []);
            $recentMetrics[] = $metric;

            // Keep only last 100 metrics
            if (count($recentMetrics) > 100) {
                $recentMetrics = array_slice($recentMetrics, -100);
            }

            Cache::put($cacheKey, $recentMetrics, 60 * 60); // Cache for 1 hour

        } catch (\Exception $e) {
            // Log metric publishing error but don't fail the operation
            Log::channel('metrics')->error('Failed to publish period close metric', [
                'error' => $e->getMessage(),
                'metric' => $metric,
            ]);
        }
    }

    /**
     * Increment counter metric
     */
    private function incrementCounter(string $counter, string $companyId, array $tags = []): void
    {
        try {
            if (Redis::isConnected()) {
                $key = "counter:period-close:{$counter}:{$companyId}";
                Redis::incr($key);
                Redis::expire($key, 30 * 24 * 60 * 60); // 30 days expiration
            }

            Log::channel('metrics')->debug("Counter incremented: {$counter}", [
                'company_id' => $companyId,
                'tags' => $tags,
            ]);
        } catch (\Exception $e) {
            Log::channel('metrics')->error("Failed to increment counter: {$counter}", [
                'error' => $e->getMessage(),
                'company_id' => $companyId,
            ]);
        }
    }

    /**
     * Record duration metric
     */
    private function recordDuration(string $duration, float $value, string $companyId, array $tags = []): void
    {
        try {
            if (Redis::isConnected()) {
                $key = "histogram:period-close:{$duration}:{$companyId}";
                $tagsJson = json_encode($tags);

                // Simple histogram implementation (5 buckets)
                $buckets = [100, 500, 1000, 5000, 30000]; // in milliseconds
                foreach ($buckets as $bucket) {
                    if ($value <= $bucket) {
                        Redis::hincrby($key, "le_{$bucket}_{$tagsJson}", 1);
                        break;
                    }
                }

                Redis::expire($key, 30 * 24 * 60 * 60); // 30 days expiration
            }

            Log::channel('metrics')->debug("Duration recorded: {$duration}", [
                'value_ms' => $value,
                'company_id' => $companyId,
                'tags' => $tags,
            ]);
        } catch (\Exception $e) {
            Log::channel('metrics')->error("Failed to record duration: {$duration}", [
                'error' => $e->getMessage(),
                'value' => $value,
                'company_id' => $companyId,
            ]);
        }
    }

    /**
     * Record gauge metric
     */
    private function recordGauge(string $gauge, float $value, string $companyId, array $tags = []): void
    {
        try {
            if (Redis::isConnected()) {
                $key = "gauge:period-close:{$gauge}:{$companyId}";
                $tagsJson = json_encode($tags);

                Redis::hset($key, "current_value_{$tagsJson}", $value);
                Redis::hset($key, "updated_at_{$tagsJson}", now()->toISOString());
                Redis::expire($key, 30 * 24 * 60 * 60); // 30 days expiration
            }

            Log::channel('metrics')->debug("Gauge recorded: {$gauge}", [
                'value' => $value,
                'company_id' => $companyId,
                'tags' => $tags,
            ]);
        } catch (\Exception $e) {
            Log::channel('metrics')->error("Failed to record gauge: {$gauge}", [
                'error' => $e->getMessage(),
                'value' => $value,
                'company_id' => $companyId,
            ]);
        }
    }

    /**
     * Get metrics for monitoring dashboard
     */
    public function getMetrics(string $companyId, array $filters = []): array
    {
        try {
            $today = now()->format('Y-m-d');
            $keyPrefix = "metrics:period-close:company:{$companyId}";

            // Get daily stats
            $dailyStats = Redis::hgetall("{$keyPrefix}:daily:{$today}") ?? [];

            // Get current gauges
            $currentGauges = [];
            $gaugeKeys = Redis::keys("gauge:period-close:*:{$companyId}");
            foreach ($gaugeKeys as $key) {
                $gaugeName = str_replace('gauge:period-close:', '', $key);
                $gaugeName = str_replace(":{$companyId}", '', $gaugeName);

                $gaugeData = Redis::hgetall($key);
                $currentGauges[$gaugeName] = $gaugeData;
            }

            // Get recent metrics from cache
            $recentMetrics = Cache::get("company_metrics:{$companyId}:recent", []);

            return [
                'company_id' => $companyId,
                'timestamp' => now()->toISOString(),
                'daily_stats' => $dailyStats,
                'current_gauges' => $currentGauges,
                'recent_metrics' => array_slice($recentMetrics, -20), // Last 20 metrics
                'performance_summary' => $this->calculatePerformanceSummary($recentMetrics, $filters),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get period close metrics', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);

            return [
                'company_id' => $companyId,
                'timestamp' => now()->toISOString(),
                'error' => 'Failed to retrieve metrics',
            ];
        }
    }

    /**
     * Calculate performance summary from recent metrics
     */
    private function calculatePerformanceSummary(array $recentMetrics, array $filters): array
    {
        $summary = [
            'avg_completion_time' => 0,
            'completion_rate' => 100,
            'validation_score_avg' => 100,
            'total_operations' => count($recentMetrics),
            'error_rate' => 0,
        ];

        if (empty($recentMetrics)) {
            return $summary;
        }

        $totalDuration = 0;
        $durationCount = 0;
        $completionRates = [];
        $validationScores = [];
        $errorCount = 0;

        foreach ($recentMetrics as $metric) {
            if (isset($metric['event'])) {
                switch ($metric['event']) {
                    case 'period_close.completed':
                        if (isset($metric['total_duration_ms'])) {
                            $totalDuration += $metric['total_duration_ms'];
                            $durationCount++;
                        }
                        if (isset($metric['final_task_completion_rate'])) {
                            $completionRates[] = $metric['final_task_completion_rate'];
                        }
                        break;
                    case 'period_close.validation_executed':
                        if (isset($metric['validation_score'])) {
                            $validationScores[] = $metric['validation_score'];
                        }
                        break;
                    case 'period_close.error':
                        $errorCount++;
                        break;
                }
            }
        }

        if ($durationCount > 0) {
            $summary['avg_completion_time'] = round($totalDuration / $durationCount / 1000, 2); // Convert to seconds
        }

        if (! empty($completionRates)) {
            $summary['completion_rate'] = round(array_sum($completionRates) / count($completionRates), 2);
        }

        if (! empty($validationScores)) {
            $summary['validation_score_avg'] = round(array_sum($validationScores) / count($validationScores), 2);
        }

        if ($summary['total_operations'] > 0) {
            $summary['error_rate'] = round(($errorCount / $summary['total_operations']) * 100, 2);
        }

        return $summary;
    }

    /**
     * Record performance threshold alerts
     */
    private function checkPerformanceThresholds(array $metric, string $companyId): void
    {
        $thresholds = $this->getPerformanceThresholds($companyId);

        // Check for slow operations
        if (isset($metric['duration_ms']) && $metric['duration_ms'] > $thresholds['slow_operation_threshold']) {
            Log::channel('alerts')->warning('Slow period close operation detected', [
                'company_id' => $companyId,
                'metric' => $metric,
                'threshold' => $thresholds['slow_operation_threshold'],
            ]);
        }

        // Check for high error rates
        $errorRate = $this->calculateRecentErrorRate($companyId);
        if ($errorRate > $thresholds['max_error_rate']) {
            Log::channel('alerts')->critical('High error rate in period close operations', [
                'company_id' => $companyId,
                'error_rate' => $errorRate,
                'threshold' => $thresholds['max_error_rate'],
            ]);
        }
    }

    /**
     * Get performance thresholds for a company
     */
    private function getPerformanceThresholds(string $companyId): array
    {
        // Default thresholds
        $thresholds = [
            'slow_operation_threshold' => 30000, // 30 seconds
            'max_error_rate' => 5.0, // 5%
        ];

        // Company-specific thresholds (if configured)
        $companyThresholds = Cache::get("company_thresholds:{$companyId}");
        if ($companyThresholds) {
            $thresholds = array_merge($thresholds, $companyThresholds);
        }

        return $thresholds;
    }

    /**
     * Calculate recent error rate for a company
     */
    private function calculateRecentErrorRate(string $companyId): float
    {
        $recentMetrics = Cache::get("company_metrics:{$companyId}:recent", []);

        if (empty($recentMetrics)) {
            return 0.0;
        }

        $errorCount = 0;
        $totalOperations = count($recentMetrics);

        foreach (array_slice($recentMetrics, -50) as $metric) { // Last 50 metrics
            if (isset($metric['event']) && str_contains($metric['event'], 'error')) {
                $errorCount++;
            }
        }

        return $totalOperations > 0 ? round(($errorCount / $totalOperations) * 100, 2) : 0.0;
    }

    /**
     * Get accounting period by ID
     *
     * Note: The accounting_periods table doesn't exist in current schema.
     * This method creates a mock period structure based on the period ID.
     * In a full implementation, we would need to create the accounting_periods table.
     */
    protected function getPeriod(string $periodId): object
    {
        $companyId = current_setting('app.current_company_id', true);

        // Extract date information from period ID if it follows a pattern like "2024-01"
        // or create a default period based on current month
        if (preg_match('/(\d{4})-(\d{1,2})/', $periodId, $matches)) {
            $year = $matches[1];
            $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            $startDate = "{$year}-{$month}-01";
            $endDate = date('Y-m-t', strtotime($startDate));
        } else {
            // Default to current month
            $startDate = now()->startOfMonth()->toDateString();
            $endDate = now()->endOfMonth()->toDateString();
        }

        return (object) [
            'id' => $periodId,
            'company_id' => $companyId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'frequency' => 'monthly',
            'status' => 'active',
        ];
    }
}
