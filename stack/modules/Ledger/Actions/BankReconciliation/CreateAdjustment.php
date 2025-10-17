<?php

namespace Modules\Ledger\Actions\BankReconciliation;

use App\Models\BankReconciliation;
use App\Models\BankReconciliationAdjustment;
use App\Models\User;
use Modules\Ledger\Services\BankReconciliationAdjustmentService;

class CreateAdjustment
{
    public function __construct(
        private readonly BankReconciliation $reconciliation,
        private readonly User $user,
        private readonly string $adjustmentType,
        private readonly float $amount,
        private readonly string $description,
        private readonly ?string $statementLineId = null,
        private readonly bool $postJournalEntry = true,
        private readonly ?string $journalEntryId = null
    ) {}

    public function execute(): BankReconciliationAdjustment
    {
        $this->validateReconciliationState();
        $this->validateAdjustmentData();
        $this->validatePermissions();

        $service = new BankReconciliationAdjustmentService;

        if ($this->journalEntryId) {
            // Link to existing journal entry
            return $service->createWithExistingJournalEntry(
                $this->reconciliation,
                $this->adjustmentType,
                $this->amount,
                $this->description,
                $this->user,
                $this->statementLineId,
                $this->journalEntryId
            );
        } else {
            // Create new journal entry or no journal entry
            return $service->createAdjustment(
                $this->reconciliation,
                $this->adjustmentType,
                $this->amount,
                $this->description,
                $this->user,
                $this->statementLineId,
                $this->postJournalEntry
            );
        }
    }

    private function validateReconciliationState(): void
    {
        if ($this->reconciliation->company_id !== $this->user->current_company_id) {
            throw new \InvalidArgumentException('Reconciliation does not belong to the current company');
        }

        if (! $this->reconciliation->isActive()) {
            throw new \InvalidArgumentException('Adjustments can only be made on active reconciliations');
        }
    }

    private function validateAdjustmentData(): void
    {
        $validTypes = ['bank_fee', 'interest', 'write_off', 'timing'];

        if (! in_array($this->adjustmentType, $validTypes)) {
            throw new \InvalidArgumentException("Invalid adjustment type: {$this->adjustmentType}");
        }

        if (empty(trim($this->description))) {
            throw new \InvalidArgumentException('Description cannot be empty');
        }

        // Validate amount sign based on adjustment type
        $this->validateAmountSign();
    }

    private function validateAmountSign(): void
    {
        switch ($this->adjustmentType) {
            case 'bank_fee':
            case 'write_off':
                if ($this->amount > 0) {
                    throw new \InvalidArgumentException('Bank fees and write-offs must be negative amounts');
                }
                break;
            case 'interest':
                if ($this->amount < 0) {
                    throw new \InvalidArgumentException('Interest income must be a positive amount');
                }
                break;
            case 'timing':
                // Timing adjustments can be positive or negative
                break;
        }
    }

    private function validatePermissions(): void
    {
        if (! $this->user->can('bank_reconciliation_adjustments.create')) {
            throw new \InvalidArgumentException('You do not have permission to create adjustments');
        }
    }

    public static function fromRequest(BankReconciliation $reconciliation, User $user, array $data): self
    {
        return new self(
            reconciliation: $reconciliation,
            user: $user,
            adjustmentType: $data['adjustment_type'],
            amount: (float) $data['amount'],
            description: $data['description'],
            statementLineId: $data['statement_line_id'] ?? null,
            postJournalEntry: $data['post_journal_entry'] ?? true,
            journalEntryId: $data['journal_entry_id'] ?? null,
        );
    }
}
