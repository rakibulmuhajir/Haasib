<?php

namespace Modules\Ledger\Actions\BankReconciliation;

use App\Models\BankReconciliation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

class LockReconciliation
{
    public function __construct(
        private BankReconciliation $reconciliation
    ) {
        $this->validate();
    }

    public function handle(): bool
    {
        $this->validate();

        return DB::transaction(function () {
            // Update reconciliation status
            $this->reconciliation->update([
                'status' => 'locked',
                'locked_at' => now(),
            ]);

            // Fire locking event
            Event::dispatch('bank.reconciliation.locked', [
                'reconciliation' => $this->reconciliation,
            ]);

            // Log locking activity
            activity()
                ->performedOn($this->reconciliation)
                ->withProperties([
                    'reconciliation_id' => $this->reconciliation->id,
                    'statement_id' => $this->reconciliation->statement_id,
                    'locked_at' => now()->toISOString(),
                ])
                ->log('Bank reconciliation locked');

            return true;
        });
    }

    private function validate(): void
    {
        // Check if reconciliation can be locked
        if (! $this->reconciliation->canBeLocked()) {
            if ($this->reconciliation->status !== 'completed') {
                throw new InvalidArgumentException('Reconciliation must be completed before it can be locked');
            }
        }

        // Check for system maintenance or locks
        if ($this->reconciliation->status === 'maintenance_locked') {
            throw new InvalidArgumentException('Cannot lock reconciliation during maintenance');
        }
    }

    public static function forReconciliation(BankReconciliation $reconciliation): self
    {
        return new self($reconciliation);
    }

    /**
     * Get validation rules for locking
     */
    public function getValidationRules(): array
    {
        return [
            'status' => 'required|in:completed',
        ];
    }

    /**
     * Get locking summary for logging/notification
     */
    public function getLockingSummary(): array
    {
        return [
            'reconciliation_id' => $this->reconciliation->id,
            'statement_period' => $this->reconciliation->statement->statement_period,
            'bank_account' => $this->reconciliation->ledgerAccount->name,
            'previous_status' => 'this->reconciliation->status',
            'new_status' => 'locked',
            'locked_at' => now()->toISOString(),
            'completed_at' => $this->reconciliation->completed_at?->toISOString(),
            'days_since_completion' => $this->reconciliation->completed_at ?
                $this->reconciliation->completed_at->diffInDays(now()) : null,
        ];
    }
}
