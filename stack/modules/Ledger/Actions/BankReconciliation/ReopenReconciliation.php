<?php

namespace Modules\Ledger\Actions\BankReconciliation;

use App\Models\BankReconciliation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

class ReopenReconciliation
{
    public function __construct(
        private BankReconciliation $reconciliation,
        private User $user,
        private string $reason
    ) {
        $this->validate();
    }

    public function handle(): bool
    {
        $this->validate();

        return DB::transaction(function () {
            // Store previous status for audit
            $previousStatus = $this->reconciliation->status;
            $previousNotes = $this->reconciliation->notes;

            // Build new notes
            $reopenEntry = sprintf(
                'Reopened at %s by %s. Reason: %s',
                now()->toDateTimeString(),
                $this->user->name,
                $this->reason
            );

            $newNotes = trim($previousNotes."\n\n".$reopenEntry);

            // Update reconciliation status
            $this->reconciliation->update([
                'status' => 'reopened',
                'notes' => $newNotes,
                // Clear completion/lock timestamps
                'completed_at' => null,
                'locked_at' => null,
            ]);

            // Fire reopening event
            Event::dispatch('bank.reconciliation.reopened', [
                'reconciliation' => $this->reconciliation,
                'user' => $this->user,
                'reason' => $this->reason,
                'previous_status' => $previousStatus,
            ]);

            // Log reopening activity
            activity()
                ->performedOn($this->reconciliation)
                ->causedBy($this->user)
                ->withProperties([
                    'reconciliation_id' => $this->reconciliation->id,
                    'statement_id' => $this->reconciliation->statement_id,
                    'previous_status' => $previousStatus,
                    'reason' => $this->reason,
                    'reopened_at' => now()->toISOString(),
                ])
                ->log('Bank reconciliation reopened');

            // Optional: Send notification to relevant users
            $this->notifyReopening();

            return true;
        });
    }

    private function validate(): void
    {
        // Validate reason
        if (empty(trim($this->reason))) {
            throw new InvalidArgumentException('Reopening reason is required');
        }

        if (strlen($this->reason) > 1000) {
            throw new InvalidArgumentException('Reopening reason must be less than 1000 characters');
        }

        // Check if reconciliation can be reopened
        if (! $this->reconciliation->canBeReopened()) {
            if ($this->reconciliation->status !== 'locked') {
                throw new InvalidArgumentException('Only locked reconciliations can be reopened');
            }
        }

        // Check user permissions
        if (! $this->user->can('reopen', $this->reconciliation)) {
            throw new InvalidArgumentException('User does not have permission to reopen this reconciliation');
        }

        // Validate company context
        if ($this->reconciliation->company_id !== $this->user->current_company_id) {
            throw new InvalidArgumentException('Reconciliation does not belong to user\'s current company');
        }

        // Check for system maintenance or locks
        if ($this->reconciliation->status === 'maintenance_locked') {
            throw new InvalidArgumentException('Cannot reopen reconciliation during maintenance');
        }

        // Validate business rules for reopening
        $this->validateReopeningRules();
    }

    private function validateReopeningRules(): void
    {
        // Check if reconciliation was recently completed (within 24 hours)
        if ($this->reconciliation->completed_at && $this->reconciliation->completed_at->diffInHours(now()) < 24) {
            // This might be allowed but should be flagged for review
            activity()
                ->performedOn($this->reconciliation)
                ->causedBy($this->user)
                ->withProperties([
                    'completed_at' => $this->reconciliation->completed_at,
                    'reopened_at' => now(),
                    'hours_since_completion' => $this->reconciliation->completed_at->diffInHours(now()),
                ])
                ->log('Bank reconciliation reopened within 24 hours of completion');
        }

        // Check if reconciliation is from a previous accounting period
        if ($this->reconciliation->statement) {
            $statementDate = $this->reconciliation->statement->statement_date;
            $currentPeriodEnd = now()->endOfMonth();

            if ($statementDate->lt($currentPeriodEnd->copy()->subMonths(1))) {
                activity()
                    ->performedOn($this->reconciliation)
                    ->causedBy($this->user)
                    ->withProperties([
                        'statement_date' => $statementDate->toDateString(),
                        'current_period' => $currentPeriodEnd->toDateString(),
                    ])
                    ->log('Bank reconciliation reopened from previous accounting period');
            }
        }
    }

    private function notifyReopening(): void
    {
        // This could send notifications to:
        // - The original user who completed the reconciliation
        // - System administrators
        // - Accounting managers

        // For now, just log the notification intent
        activity()
            ->performedOn($this->reconciliation)
            ->causedBy($this->user)
            ->withProperties([
                'original_completed_by' => $this->reconciliation->completed_by,
                'reopening_reason' => $this->reason,
            ])
            ->log('Reopening notification queued');
    }

    public static function forReconciliation(
        BankReconciliation $reconciliation,
        User $user,
        string $reason
    ): self {
        return new self($reconciliation, $user, $reason);
    }

    /**
     * Get validation rules for reopening
     */
    public function getValidationRules(): array
    {
        return [
            'status' => 'required|in:locked',
            'reason' => 'required|string|min:5|max:1000',
            'company_id' => 'required|exists:companies,id',
        ];
    }

    /**
     * Get reopening summary for logging/notification
     */
    public function getReopeningSummary(): array
    {
        return [
            'reconciliation_id' => $this->reconciliation->id,
            'statement_period' => $this->reconciliation->statement->statement_period,
            'bank_account' => $this->reconciliation->ledgerAccount->name,
            'previous_status' => $this->reconciliation->status,
            'new_status' => 'reopened',
            'reason' => $this->reason,
            'reopened_by' => $this->user->name,
            'reopened_at' => now()->toISOString(),
            'original_completed_at' => $this->reconciliation->completed_at?->toISOString(),
            'days_since_completion' => $this->reconciliation->completed_at ?
                $this->reconciliation->completed_at->diffInDays(now()) : null,
        ];
    }

    /**
     * Check if reopening requires approval
     */
    public function requiresApproval(): bool
    {
        // Reopening requires approval if:
        // 1. Reconciliation was completed more than 30 days ago
        // 2. Original completing user was different
        // 3. Reconciliation is from a closed accounting period

        if ($this->reconciliation->completed_at &&
            $this->reconciliation->completed_at->diffInDays(now()) > 30) {
            return true;
        }

        if ($this->reconciliation->completed_by !== $this->user->id) {
            return true;
        }

        // Check if statement period is closed
        if ($this->reconciliation->statement) {
            $statementDate = $this->reconciliation->statement->statement_date;
            $currentPeriodStart = now()->startOfMonth();

            if ($statementDate->lt($currentPeriodStart)) {
                return true;
            }
        }

        return false;
    }
}
