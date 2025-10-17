<?php

namespace Modules\Ledger\Actions\BankReconciliation;

use App\Models\BankReconciliation;
use App\Models\User;
use Modules\Ledger\Jobs\RunAutoMatchJob;
use Modules\Ledger\Services\BankReconciliationMatchingService;

class RunAutoMatch
{
    public function __construct(
        private readonly BankReconciliation $reconciliation,
        private readonly User $user,
        private readonly array $options = []
    ) {}

    public function execute(): int
    {
        $this->validateReconciliationState();

        $service = new BankReconciliationMatchingService;
        $matches = $service->runAutoMatch($this->reconciliation, $this->options);

        // Update reconciliation variance
        $this->reconciliation->recalculateVariance();

        return $matches->count();
    }

    public function executeAsync(): string
    {
        $this->validateReconciliationState();

        return RunAutoMatchJob::dispatch($this->reconciliation, $this->user, $this->options);
    }

    private function validateReconciliationState(): void
    {
        if ($this->reconciliation->company_id !== $this->user->current_company_id) {
            throw new \InvalidArgumentException('Reconciliation does not belong to the current company');
        }

        if (! $this->reconciliation->isActive()) {
            throw new \InvalidArgumentException('Auto-matching can only be run on active reconciliations');
        }

        if ($this->reconciliation->statement?->status !== 'processed') {
            throw new \InvalidArgumentException('Statement must be processed before running auto-match');
        }
    }

    public static function forReconciliation(BankReconciliation $reconciliation, User $user, array $options = []): self
    {
        return new self($reconciliation, $user, $options);
    }
}
