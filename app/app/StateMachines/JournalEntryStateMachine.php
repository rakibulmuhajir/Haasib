<?php

namespace App\StateMachines;

use App\Exceptions\UnbalancedJournalException;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\Log;

class JournalEntryStateMachine
{
    protected JournalEntry $journalEntry;

    /**
     * Events that should be dispatched for each transition.
     */
    protected array $dispatchesEvents = [
        'posted' => \App\Events\Ledger\JournalEntryPosted::class,
        'void' => \App\Events\Ledger\JournalEntryVoided::class,
        'cancelled' => \App\Events\Ledger\JournalEntryCancelled::class,
    ];

    protected array $transitions = [
        'draft' => ['posted', 'cancelled'],
        'posted' => ['void'],
        'void' => [],
        'cancelled' => [],
    ];

    public function __construct(JournalEntry $journalEntry)
    {
        $this->journalEntry = $journalEntry;
    }

    public function canTransitionTo(string $newStatus): bool
    {
        return in_array($newStatus, $this->transitions[$this->journalEntry->status] ?? []);
    }

    public function transitionTo(string $newStatus, array $context = []): void
    {
        if (! $this->canTransitionTo($newStatus)) {
            throw new \InvalidArgumentException("Cannot transition Journal Entry from [{$this->journalEntry->status}] to [{$newStatus}].");
        }

        $this->validateTransition($newStatus);

        $oldStatus = $this->journalEntry->status;

        $this->journalEntry->status = $newStatus;

        $this->applyStateSideEffects($newStatus, $context);

        $this->logStatusTransition($oldStatus, $newStatus, $context['reason'] ?? null);

        $this->journalEntry->save();

        // Dispatch event if defined, after the state is saved.
        if (isset($this->dispatchesEvents[$newStatus])) {
            event(new $this->dispatchesEvents[$newStatus]($this->journalEntry, $context));
        }
    }

    protected function validateTransition(string $newStatus): void
    {
        if ($newStatus === 'posted') {
            $this->journalEntry->calculateTotals();
            if (! $this->journalEntry->isBalanced()) {
                throw new UnbalancedJournalException('Journal entry is not balanced. Debits must equal credits.');
            }
            if ($this->journalEntry->journalLines()->count() < 2) {
                throw new \InvalidArgumentException('A journal entry must have at least two lines to be posted.');
            }
        }
    }

    protected function applyStateSideEffects(string $newStatus, array $context): void
    {
        $metadata = $this->journalEntry->metadata ?? [];

        if ($newStatus === 'posted') {
            $this->journalEntry->posted_at = now();
            $this->journalEntry->posted_by_user_id = auth()->id();
        }

        if ($newStatus === 'void') {
            $metadata['voided_at'] = now()->toISOString();
            $metadata['voided_by_user_id'] = auth()->id();
            $metadata['void_reason'] = $context['reason'] ?? 'No reason provided.';
            $metadata['reversing_entry_id'] = $context['reversing_entry_id'] ?? null;
        }

        $this->journalEntry->metadata = $metadata;
    }

    private function logStatusTransition(string $oldStatus, string $newStatus, ?string $reason = null): void
    {
        Log::info('Journal Entry status transition', [
            'journal_entry_id' => $this->journalEntry->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'reason' => $reason,
            'user_id' => auth()->id(),
        ]);
    }
}
