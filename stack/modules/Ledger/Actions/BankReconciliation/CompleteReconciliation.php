<?php

namespace Modules\Ledger\Actions\BankReconciliation;

use App\Models\BankReconciliation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

class CompleteReconciliation
{
    public function __construct(
        private BankReconciliation $reconciliation,
        private User $user
    ) {
        $this->validate();
    }

    public function handle(): bool
    {
        $this->validate();

        return DB::transaction(function () {
            // Update reconciliation status
            $this->reconciliation->update([
                'status' => 'completed',
                'completed_by' => $this->user->id,
                'completed_at' => now(),
            ]);

            // Fire completion event
            Event::dispatch('bank.reconciliation.completed', [
                'reconciliation' => $this->reconciliation,
                'user' => $this->user,
            ]);

            // Log completion activity
            activity()
                ->performedOn($this->reconciliation)
                ->causedBy($this->user)
                ->withProperties([
                    'reconciliation_id' => $this->reconciliation->id,
                    'statement_id' => $this->reconciliation->statement_id,
                    'variance' => $this->reconciliation->variance,
                    'completed_at' => now()->toISOString(),
                ])
                ->log('Bank reconciliation completed');

            return true;
        });
    }

    private function validate(): void
    {
        // Check if reconciliation can be completed
        if (! $this->reconciliation->canBeCompleted()) {
            if ($this->reconciliation->status !== 'in_progress') {
                throw new InvalidArgumentException('Reconciliation must be in progress to be completed');
            }

            if ($this->reconciliation->variance != 0) {
                throw new InvalidArgumentException(
                    'Reconciliation cannot be completed. Variance must be zero. Current variance: '.
                    $this->reconciliation->formatted_variance
                );
            }
        }

        // Check user permissions
        if (! $this->user->can('complete', $this->reconciliation)) {
            throw new InvalidArgumentException('User does not have permission to complete this reconciliation');
        }

        // Validate company context
        if ($this->reconciliation->company_id !== $this->user->current_company_id) {
            throw new InvalidArgumentException('Reconciliation does not belong to user\'s current company');
        }

        // Check for system maintenance or locks
        if ($this->reconciliation->status === 'maintenance_locked') {
            throw new InvalidArgumentException('Cannot complete reconciliation during maintenance');
        }

        // Ensure statement is properly loaded
        if (! $this->reconciliation->relationLoaded('statement')) {
            $this->reconciliation->load('statement');
        }

        // Validate statement data integrity
        $this->validateStatementIntegrity();
    }

    private function validateStatementIntegrity(): void
    {
        $statement = $this->reconciliation->statement;

        if (! $statement) {
            throw new InvalidArgumentException('Bank statement not found for this reconciliation');
        }

        // Validate statement period makes sense
        if ($statement->opening_balance > $statement->closing_balance) {
            // This could be valid (negative period), but log it for audit
            activity()
                ->performedOn($this->reconciliation)
                ->causedBy($this->user)
                ->withProperties([
                    'opening_balance' => $statement->opening_balance,
                    'closing_balance' => $statement->closing_balance,
                    'period_amount' => $statement->closing_balance - $statement->opening_balance,
                ])
                ->log('Bank reconciliation completed with negative period amount');
        }

        // Validate that statement has lines
        if ($statement->bankStatementLines()->count() === 0) {
            throw new InvalidArgumentException('Cannot complete reconciliation with empty statement');
        }
    }

    public static function forReconciliation(BankReconciliation $reconciliation, User $user): self
    {
        return new self($reconciliation, $user);
    }

    /**
     * Get validation rules for completion
     */
    public function getValidationRules(): array
    {
        return [
            'status' => 'required|in:in_progress',
            'variance' => 'required|numeric|exact:0',
            'company_id' => 'required|exists:companies,id',
        ];
    }

    /**
     * Get completion summary for logging/notification
     */
    public function getCompletionSummary(): array
    {
        return [
            'reconciliation_id' => $this->reconciliation->id,
            'statement_period' => $this->reconciliation->statement->statement_period,
            'bank_account' => $this->reconciliation->ledgerAccount->name,
            'statement_lines_count' => $this->reconciliation->statement->bankStatementLines()->count(),
            'matches_count' => $this->reconciliation->matches()->count(),
            'adjustments_count' => $this->reconciliation->adjustments()->count(),
            'variance' => $this->reconciliation->formatted_variance,
            'completed_by' => $this->user->name,
            'completed_at' => now()->toISOString(),
            'active_duration' => $this->reconciliation->active_duration,
        ];
    }
}
