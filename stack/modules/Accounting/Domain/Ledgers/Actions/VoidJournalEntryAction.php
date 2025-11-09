<?php

namespace Modules\Accounting\Domain\Ledgers\Actions;

use App\Models\JournalAudit;
use App\Models\JournalEntry;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class VoidJournalEntryAction
{
    /**
     * Void a journal entry.
     *
     * @throws ValidationException
     * @throws Exception
     */
    public function execute(string $journalEntryId, string $voidReason): JournalEntry
    {
        // Validate input
        $validator = Validator::make([
            'journal_entry_id' => $journalEntryId,
            'void_reason' => $voidReason,
        ], [
            'journal_entry_id' => 'required|uuid|exists:pgsql.acct.journal_entries,id',
            'void_reason' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        return DB::transaction(function () use ($validated) {
            // Find and lock the journal entry
            $journalEntry = JournalEntry::where('id', $validated['journal_entry_id'])
                ->lockForUpdate()
                ->firstOrFail();

            // Validate business rules
            $this->validateCanVoid($journalEntry);

            // If entry is posted, reverse account balances
            if ($journalEntry->isPosted()) {
                $this->reverseAccountBalances($journalEntry);
            }

            // Update journal entry
            $journalEntry->update([
                'status' => 'void',
                'voided_by' => auth()->id(),
                'voided_at' => now(),
                'void_reason' => $validated['void_reason'],
            ]);

            // Create audit record
            JournalAudit::createEvent(
                $journalEntry->id,
                'voided',
                [
                    'previous_state' => [
                        'status' => $journalEntry->getOriginal('status'),
                    ],
                    'new_state' => [
                        'status' => 'void',
                        'voided_by' => auth()->id(),
                        'voided_at' => now()->toISOString(),
                        'void_reason' => $validated['void_reason'],
                    ],
                    'changes' => [
                        'status' => ['from' => $journalEntry->getOriginal('status'), 'to' => 'void'],
                        'voided_by' => ['from' => $journalEntry->getOriginal('voided_by'), 'to' => auth()->id()],
                        'voided_at' => ['from' => $journalEntry->getOriginal('voided_at'), 'to' => now()->toISOString()],
                        'void_reason' => ['from' => $journalEntry->getOriginal('void_reason'), 'to' => $validated['void_reason']],
                    ],
                    'metadata' => [
                        'action' => 'void',
                        'was_posted' => $journalEntry->isPosted(),
                    ],
                ],
                auth()->id()
            );

            return $journalEntry->fresh();
        });
    }

    /**
     * Validate that the journal entry can be voided.
     *
     * @throws Exception
     */
    private function validateCanVoid(JournalEntry $journalEntry): void
    {
        // Check if entry is already void
        if ($journalEntry->status === 'void') {
            throw new Exception('Journal entry is already void');
        }

        // Check if user has permission to void entries for this company
        if (! $this->userCanVoidForCompany($journalEntry->company_id)) {
            throw new Exception('You do not have permission to void journal entries for this company');
        }

        // Check if entry already has a reversal
        if ($journalEntry->reversal_entry_id) {
            throw new Exception('Cannot void an entry that has been reversed');
        }

        // Check if entry is a reversal of another entry
        if ($journalEntry->reverse_of_entry_id) {
            throw new Exception('Cannot void a reversal entry');
        }

        // Check for time-based restrictions
        if ($this->isTooOldToVoid($journalEntry)) {
            throw new Exception('Journal entry is too old to be voided');
        }

        // Check if voiding would violate accounting rules
        $this->validateVoidRules($journalEntry);
    }

    /**
     * Reverse account balances for a posted entry being voided.
     */
    private function reverseAccountBalances(JournalEntry $journalEntry): void
    {
        foreach ($journalEntry->transactions as $transaction) {
            $account = $transaction->account;
            $currentBalance = $account->current_balance ?? 0;

            // Voiding means undoing the transaction
            $change = $transaction->amount;

            if ($account->normal_balance === 'debit') {
                $newBalance = $transaction->debit_credit === 'debit'
                    ? $currentBalance - $change  // Reverse the debit
                    : $currentBalance + $change; // Reverse the credit
            } else {
                $newBalance = $transaction->debit_credit === 'credit'
                    ? $currentBalance - $change  // Reverse the credit
                    : $currentBalance + $change; // Reverse the debit
            }

            $account->update([
                'current_balance' => $newBalance,
                'last_updated_at' => now(),
            ]);

            // Log balance change
            $this->logBalanceChange($account, $currentBalance, $newBalance, $transaction, 'void');
        }
    }

    /**
     * Validate voiding-specific business rules.
     *
     * @throws Exception
     */
    private function validateVoidRules(JournalEntry $journalEntry): void
    {
        // Check if voiding would create negative balances
        if ($journalEntry->isPosted()) {
            foreach ($journalEntry->transactions as $transaction) {
                $account = $transaction->account;
                $currentBalance = $account->current_balance ?? 0;

                // Calculate new balance after voiding
                $change = $transaction->amount;

                if ($account->normal_balance === 'debit') {
                    $newBalance = $transaction->debit_credit === 'debit'
                        ? $currentBalance - $change
                        : $currentBalance + $change;
                } else {
                    $newBalance = $transaction->debit_credit === 'credit'
                        ? $currentBalance - $change
                        : $currentBalance + $change;
                }

                // Check if this would violate balance constraints
                if (in_array($account->account_type, ['asset', 'liability', 'equity']) && $newBalance < 0) {
                    throw new Exception("Voiding this entry would create a negative balance for account {$account->account_code}");
                }
            }
        }

        // Check if there are subsequent entries that depend on this one
        if ($this->hasDependentEntries($journalEntry)) {
            throw new Exception('Cannot void an entry that has dependent entries');
        }

        // Check if entry is part of a batch that has been posted
        if ($journalEntry->batch && $journalEntry->batch->isPosted()) {
            throw new Exception('Cannot void an individual entry from a posted batch. Void the entire batch instead.');
        }
    }

    /**
     * Check if there are entries that depend on this one.
     */
    private function hasDependentEntries(JournalEntry $journalEntry): bool
    {
        // Check for entries posted after this one that reference the same accounts
        return JournalEntry::where('company_id', $journalEntry->company_id)
            ->where('date', '>', $journalEntry->date)
            ->where('status', 'posted')
            ->whereHas('transactions', function ($query) use ($journalEntry) {
                $accountIds = $journalEntry->transactions->pluck('account_id');
                $query->whereIn('account_id', $accountIds);
            })
            ->exists();
    }

    /**
     * Check if the entry is too old to void.
     */
    private function isTooOldToVoid(JournalEntry $journalEntry): bool
    {
        // Allow voiding entries up to 1 year old
        $oneYearAgo = now()->subYear();
        $entryDate = $journalEntry->posted_at ?? $journalEntry->created_at;

        return $entryDate->lessThan($oneYearAgo);
    }

    /**
     * Check if the user has permission to void entries for the company.
     */
    private function userCanVoidForCompany(string $companyId): bool
    {
        $user = auth()->user();

        // System owners can void for any company
        if ($user->hasRole('system_owner')) {
            return true;
        }

        // Company owners can void for their own company
        if ($user->hasRole('company_owner') && $user->companies()->where('id', $companyId)->exists()) {
            return true;
        }

        // Accountants can void if they have the permission
        if ($user->hasRole('accountant') && $user->hasPermission('accounting.journal_entries.void')) {
            return true;
        }

        return false;
    }

    /**
     * Log balance change for audit trail.
     *
     * @param  mixed  $account
     * @param  mixed  $transaction
     */
    private function logBalanceChange($account, float $oldBalance, float $newBalance, $transaction, string $reason): void
    {
        JournalAudit::createEvent(
            $transaction->journal_entry_id,
            'updated',
            [
                'metadata' => [
                    'action' => 'account_balance_update',
                    'account_id' => $account->id,
                    'account_code' => $account->code,
                    'old_balance' => $oldBalance,
                    'new_balance' => $newBalance,
                    'change_amount' => $transaction->amount,
                    'change_type' => $transaction->debit_credit,
                    'reason' => $reason,
                ],
                'actor_id' => auth()->id(),
            ],
            auth()->id()
        );
    }
}
