<?php

namespace Modules\Accounting\Domain\Ledgers\Actions;

use App\Models\JournalAudit;
use App\Models\JournalEntry;
use App\Models\JournalEntrySource;
use App\Models\JournalTransaction;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReverseJournalEntryAction
{
    /**
     * Create a reversing journal entry.
     *
     * @throws ValidationException
     * @throws Exception
     */
    public function execute(string $journalEntryId, array $options = []): array
    {
        // Default options
        $options = array_merge([
            'reversal_date' => now()->toDateString(),
            'description_override' => null,
            'auto_post' => false,
            'reversal_reason' => null,
        ], $options);

        // Validate input
        $validator = Validator::make(array_merge(['journal_entry_id' => $journalEntryId], $options), [
            'journal_entry_id' => 'required|uuid|exists:acct.journal_entries,id',
            'reversal_date' => 'required|date|after_or_equal:'.now()->toDateString(),
            'description_override' => 'nullable|string|max:500',
            'auto_post' => 'boolean',
            'reversal_reason' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        return DB::transaction(function () use ($validated) {
            // Find and lock the original journal entry
            $originalEntry = JournalEntry::where('id', $validated['journal_entry_id'])
                ->with(['transactions', 'company'])
                ->lockForUpdate()
                ->firstOrFail();

            // Validate business rules
            $this->validateCanReverse($originalEntry);

            // Create reversing journal entry
            $reversalEntry = $this->createReversalEntry($originalEntry, $validated);

            // Create reversing transactions
            $reversalTransactions = $this->createReversalTransactions($originalEntry, $reversalEntry);

            // Link original and reversal entries
            $this->linkReversalEntries($originalEntry, $reversalEntry);

            // Create source records
            $this->createReversalSources($originalEntry, $reversalEntry);

            // Auto-post if requested
            if ($validated['auto_post']) {
                $this->autoPostReversal($reversalEntry);
            }

            // Create audit records
            $this->createReversalAudit($originalEntry, $reversalEntry, $validated);

            return [
                'original_entry' => $originalEntry->toArray(),
                'reversal_entry' => $reversalEntry->toArray(),
                'reversal_transactions' => $reversalTransactions,
                'auto_posted' => $validated['auto_post'],
            ];
        });
    }

    /**
     * Validate that the journal entry can be reversed.
     *
     * @throws Exception
     */
    private function validateCanReverse(JournalEntry $originalEntry): void
    {
        // Check if entry is posted
        if ($originalEntry->status !== 'posted') {
            throw new Exception('Only posted journal entries can be reversed');
        }

        // Check if user has permission to reverse entries for this company
        if (! $this->userCanReverseForCompany($originalEntry->company_id)) {
            throw new Exception('You do not have permission to reverse journal entries for this company');
        }

        // Check if entry already has a reversal
        if ($originalEntry->reversal_entry_id) {
            throw new Exception('Journal entry already has a reversal');
        }

        // Check if entry is a reversal itself (prevent reversing reversals)
        if ($originalEntry->type === 'reversal' && $originalEntry->reverse_of_entry_id) {
            throw new Exception('Cannot reverse a reversal entry');
        }

        // Check for time-based restrictions (e.g., entries older than 1 year)
        if ($this->isTooOldToReverse($originalEntry)) {
            throw new Exception('Journal entry is too old to be reversed');
        }

        // Check if reversing would violate accounting rules
        $this->validateReversalRules($originalEntry);
    }

    /**
     * Create the reversing journal entry.
     */
    private function createReversalEntry(JournalEntry $originalEntry, array $validated): JournalEntry
    {
        $description = $validated['description_override']
            ?? "REVERSAL: {$originalEntry->description}";

        return JournalEntry::create([
            'id' => (string) Str::uuid(),
            'company_id' => $originalEntry->company_id,
            'reference' => $this->generateReversalReference($originalEntry),
            'description' => $description,
            'date' => $validated['reversal_date'],
            'type' => 'reversal',
            'status' => $validated['auto_post'] ? 'posted' : 'approved',
            'currency' => $originalEntry->currency,
            'exchange_rate' => $originalEntry->exchange_rate,
            'created_by' => auth()->id(),
            'approved_by' => $validated['auto_post'] ? auth()->id() : null,
            'approved_at' => $validated['auto_post'] ? now() : null,
            'posted_by' => $validated['auto_post'] ? auth()->id() : null,
            'posted_at' => $validated['auto_post'] ? now() : null,
            'auto_generated' => false,
            'reverse_of_entry_id' => $originalEntry->id,
            'attachments' => [],
            'metadata' => [
                'original_entry_id' => $originalEntry->id,
                'original_reference' => $originalEntry->reference,
                'original_date' => $originalEntry->date->toDateString(),
                'reversal_reason' => $validated['reversal_reason'],
                'auto_posted' => $validated['auto_post'],
            ],
        ]);
    }

    /**
     * Create reversing transactions.
     */
    private function createReversalTransactions(JournalEntry $originalEntry, JournalEntry $reversalEntry): array
    {
        $reversalTransactions = [];
        $lineNumber = 1;

        foreach ($originalEntry->transactions as $originalTransaction) {
            // Invert debit/credit and amount
            $reversalTransaction = JournalTransaction::create([
                'id' => (string) Str::uuid(),
                'journal_entry_id' => $reversalEntry->id,
                'line_number' => $lineNumber++,
                'account_id' => $originalTransaction->account_id,
                'account_code' => $originalTransaction->account_code,
                'account_name' => $originalTransaction->account_name,
                'debit_credit' => $originalTransaction->debit_credit === 'debit' ? 'credit' : 'debit',
                'amount' => $originalTransaction->amount,
                'description' => "REVERSAL: {$originalTransaction->description}",
                'created_at' => now(),
            ]);

            $reversalTransactions[] = $reversalTransaction->toArray();
        }

        return $reversalTransactions;
    }

    /**
     * Link original and reversal entries.
     */
    private function linkReversalEntries(JournalEntry $originalEntry, JournalEntry $reversalEntry): void
    {
        $originalEntry->update([
            'reversal_entry_id' => $reversalEntry->id,
        ]);
    }

    /**
     * Create source records for the reversal.
     */
    private function createReversalSources(JournalEntry $originalEntry, JournalEntry $reversalEntry): void
    {
        // Link reversal to original entry
        JournalEntrySource::createSource(
            $reversalEntry->id,
            'JournalEntry',
            $originalEntry->id,
            'reversal',
            null,
            $originalEntry->reference
        );

        // Copy original sources as supporting documents
        foreach ($originalEntry->sources as $source) {
            JournalEntrySource::createSource(
                $reversalEntry->id,
                $source->source_type,
                $source->source_id,
                'supporting',
                null,
                $source->source_reference
            );
        }
    }

    /**
     * Auto-post the reversal entry.
     */
    private function autoPostReversal(JournalEntry $reversalEntry): void
    {
        // Update account balances with reversal amounts
        foreach ($reversalEntry->transactions as $transaction) {
            $account = $transaction->account;
            $currentBalance = $account->current_balance ?? 0;

            // Since this is a reversal, we're going back to the original state
            $change = $transaction->amount;

            if ($account->normal_balance === 'debit') {
                $newBalance = $transaction->debit_credit === 'debit'
                    ? $currentBalance + $change
                    : $currentBalance - $change;
            } else {
                $newBalance = $transaction->debit_credit === 'credit'
                    ? $currentBalance + $change
                    : $currentBalance - $change;
            }

            $account->update([
                'current_balance' => $newBalance,
                'last_updated_at' => now(),
            ]);
        }
    }

    /**
     * Create audit records for the reversal.
     */
    private function createReversalAudit(JournalEntry $originalEntry, JournalEntry $reversalEntry, array $validated): void
    {
        // Audit for original entry
        JournalAudit::createEvent(
            $originalEntry->id,
            'updated',
            [
                'previous_state' => [
                    'reversal_entry_id' => null,
                ],
                'new_state' => [
                    'reversal_entry_id' => $reversalEntry->id,
                ],
                'changes' => [
                    'reversal_entry_id' => ['from' => null, 'to' => $reversalEntry->id],
                ],
                'metadata' => [
                    'action' => 'reversal_created',
                    'reversal_id' => $reversalEntry->id,
                    'reversal_date' => $validated['reversal_date'],
                    'reversal_reason' => $validated['reversal_reason'],
                ],
            ],
            auth()->id()
        );

        // Audit for reversal entry creation
        JournalAudit::createEvent(
            $reversalEntry->id,
            'created',
            [
                'previous_state' => null,
                'new_state' => [
                    'status' => $reversalEntry->status,
                    'reverse_of_entry_id' => $originalEntry->id,
                ],
                'metadata' => [
                    'action' => 'create_reversal',
                    'original_entry_id' => $originalEntry->id,
                    'original_reference' => $originalEntry->reference,
                    'auto_posted' => $validated['auto_post'],
                ],
            ],
            auth()->id()
        );

        // If auto-posted, audit the posting
        if ($validated['auto_post']) {
            JournalAudit::createEvent(
                $reversalEntry->id,
                'posted',
                [
                    'previous_state' => [
                        'status' => 'approved',
                    ],
                    'new_state' => [
                        'status' => 'posted',
                        'posted_by' => auth()->id(),
                        'posted_at' => now()->toISOString(),
                    ],
                    'metadata' => [
                        'action' => 'auto_post_reversal',
                        'original_entry_id' => $originalEntry->id,
                    ],
                ],
                auth()->id()
            );
        }
    }

    /**
     * Generate a reference for the reversal entry.
     */
    private function generateReversalReference(JournalEntry $originalEntry): string
    {
        return "REV-{$originalEntry->reference}";
    }

    /**
     * Check if the entry is too old to reverse.
     */
    private function isTooOldToReverse(JournalEntry $originalEntry): bool
    {
        // Allow reversing entries up to 1 year old
        $oneYearAgo = now()->subYear();

        return $originalEntry->posted_at && $originalEntry->posted_at->lessThan($oneYearAgo);
    }

    /**
     * Validate reversal-specific business rules.
     *
     * @throws Exception
     */
    private function validateReversalRules(JournalEntry $originalEntry): void
    {
        // Check if reversing would create negative balances
        foreach ($originalEntry->transactions as $transaction) {
            $account = $transaction->account;
            $currentBalance = $account->current_balance ?? 0;

            // Reversing means going back to original state
            $reversalEffect = $transaction->debit_credit === 'debit'
                ? -$transaction->amount
                : $transaction->amount;

            $newBalance = $account->normal_balance === 'debit'
                ? $currentBalance - $reversalEffect
                : $currentBalance + $reversalEffect;

            // Check if this would violate balance constraints
            if (in_array($account->account_type, ['asset', 'liability', 'equity']) && $newBalance < 0) {
                throw new Exception("Reversing this entry would create a negative balance for account {$account->account_code}");
            }
        }

        // Check if there are subsequent entries that depend on this one
        if ($this->hasDependentEntries($originalEntry)) {
            throw new Exception('Cannot reverse an entry that has dependent entries');
        }
    }

    /**
     * Check if there are entries that depend on this one.
     */
    private function hasDependentEntries(JournalEntry $originalEntry): bool
    {
        // Check for entries posted after this one that reference the same accounts
        // with similar amounts or descriptions that might be related
        return JournalEntry::where('company_id', $originalEntry->company_id)
            ->where('date', '>', $originalEntry->date)
            ->where('status', 'posted')
            ->whereHas('transactions', function ($query) use ($originalEntry) {
                $accountIds = $originalEntry->transactions->pluck('account_id');
                $query->whereIn('account_id', $accountIds);
            })
            ->exists();
    }

    /**
     * Check if the user has permission to reverse entries for the company.
     */
    private function userCanReverseForCompany(string $companyId): bool
    {
        $user = auth()->user();

        // System owners can reverse for any company
        if ($user->hasRole('system_owner')) {
            return true;
        }

        // Company owners can reverse for their own company
        if ($user->hasRole('company_owner') && $user->companies()->where('id', $companyId)->exists()) {
            return true;
        }

        // Accountants can reverse if they have the permission
        if ($user->hasRole('accountant') && $user->hasPermission('accounting.journal_entries.reverse')) {
            return true;
        }

        return false;
    }
}
