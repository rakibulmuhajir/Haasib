<?php

namespace Modules\Accounting\Domain\Ledgers\Actions;

use App\Models\Account;
use App\Models\JournalAudit;
use App\Models\JournalEntry;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PostJournalEntryAction
{
    /**
     * Post an approved journal entry to the ledger.
     *
     * @throws ValidationException
     * @throws Exception
     */
    public function execute(string $journalEntryId, ?string $postNote = null): JournalEntry
    {
        // Validate input
        $validator = Validator::make([
            'journal_entry_id' => $journalEntryId,
            'post_note' => $postNote,
        ], [
            'journal_entry_id' => 'required|uuid|exists:pgsql.acct.journal_entries,id',
            'post_note' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        return DB::transaction(function () use ($validated) {
            // Find and lock the journal entry
            $journalEntry = JournalEntry::where('id', $validated['journal_entry_id'])
                ->with(['transactions.account'])
                ->lockForUpdate()
                ->firstOrFail();

            // Validate business rules
            $this->validateCanPost($journalEntry);

            // Update account balances
            $this->updateAccountBalances($journalEntry);

            // Update journal entry status
            $journalEntry->update([
                'status' => 'posted',
                'posted_by' => auth()->id(),
                'posted_at' => now(),
            ]);

            // Create audit record
            JournalAudit::createEvent(
                $journalEntry->id,
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
                    'changes' => [
                        'status' => ['from' => 'approved', 'to' => 'posted'],
                        'posted_by' => ['from' => null, 'to' => auth()->id()],
                        'posted_at' => ['from' => null, 'to' => now()->toISOString()],
                    ],
                    'metadata' => [
                        'action' => 'post',
                        'post_note' => $validated['post_note'],
                        'accounts_affected' => $journalEntry->transactions->pluck('account_id')->toArray(),
                    ],
                ],
                auth()->id()
            );

            return $journalEntry->fresh();
        });
    }

    /**
     * Validate that the journal entry can be posted.
     *
     * @throws Exception
     */
    private function validateCanPost(JournalEntry $journalEntry): void
    {
        // Check if entry is approved
        if ($journalEntry->status !== 'approved') {
            throw new Exception('Only approved journal entries can be posted');
        }

        // Check if user has permission to post entries for this company
        if (! $this->userCanPostForCompany($journalEntry->company_id)) {
            throw new Exception('You do not have permission to post journal entries for this company');
        }

        // Check if entry has already been posted
        if ($journalEntry->posted_at) {
            throw new Exception('Journal entry has already been posted');
        }

        // Re-validate business rules
        $this->validateBusinessRules($journalEntry);
    }

    /**
     * Validate business-specific rules for posting.
     *
     * @throws Exception
     */
    private function validateBusinessRules(JournalEntry $journalEntry): void
    {
        // Check if entry is still balanced
        if (! $journalEntry->isBalanced()) {
            throw new Exception('Journal entry must be balanced for posting');
        }

        // Check if entry date is still within allowed posting period
        if (! $this->isDateWithinPostingPeriod($journalEntry->date, $journalEntry->company_id)) {
            throw new Exception('Journal entry date is outside the allowed posting period');
        }

        // Check all accounts are still active
        foreach ($journalEntry->transactions as $transaction) {
            if (! $transaction->account->active) {
                throw new Exception("Account {$transaction->account_code} is inactive and cannot be posted to");
            }

            // Check if posting would make account balance negative (for balance sheet accounts)
            if ($this->wouldCreateNegativeBalance($transaction)) {
                throw new Exception("Posting would create a negative balance for account {$transaction->account_code}");
            }
        }

        // Check for posting conflicts with other entries
        if ($this->hasPostingConflict($journalEntry)) {
            throw new Exception('Posting conflict detected with another journal entry');
        }
    }

    /**
     * Update account balances based on journal entry transactions.
     *
     * @throws Exception
     */
    private function updateAccountBalances(JournalEntry $journalEntry): void
    {
        foreach ($journalEntry->transactions as $transaction) {
            $account = $transaction->account;
            $currentBalance = $account->current_balance ?? 0;

            // Calculate balance change
            $change = $transaction->amount;

            // For debit accounts (assets, expenses), debits increase balance
            // For credit accounts (liabilities, equity, revenue), credits increase balance
            if ($account->normal_balance === 'debit') {
                $newBalance = $transaction->debit_credit === 'debit'
                    ? $currentBalance + $change
                    : $currentBalance - $change;
            } else {
                $newBalance = $transaction->debit_credit === 'credit'
                    ? $currentBalance + $change
                    : $currentBalance - $change;
            }

            // Update account balance
            $account->update([
                'current_balance' => $newBalance,
                'last_updated_at' => now(),
            ]);

            // Log balance change for audit trail
            $this->logBalanceChange($account, $currentBalance, $newBalance, $transaction);
        }
    }

    /**
     * Check if posting would create a negative balance.
     *
     * @param  mixed  $transaction
     */
    private function wouldCreateNegativeBalance($transaction): bool
    {
        $account = $transaction->account;
        $currentBalance = $account->current_balance ?? 0;

        // Only check for balance sheet accounts (assets, liabilities, equity)
        if (in_array($account->account_type, ['asset', 'liability', 'equity'])) {
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

            return $newBalance < 0;
        }

        return false;
    }

    /**
     * Check if there are posting conflicts.
     */
    private function hasPostingConflict(JournalEntry $journalEntry): bool
    {
        // Check for other posted entries on the same date with conflicting references
        return JournalEntry::where('company_id', $journalEntry->company_id)
            ->where('date', $journalEntry->date)
            ->where('reference', $journalEntry->reference)
            ->where('status', 'posted')
            ->where('id', '!=', $journalEntry->id)
            ->exists();
    }

    /**
     * Check if the user has permission to post entries for the company.
     */
    private function userCanPostForCompany(string $companyId): bool
    {
        $user = auth()->user();

        // System owners can post for any company
        if ($user->hasRole('system_owner')) {
            return true;
        }

        // Company owners can post for their own company
        if ($user->hasRole('company_owner') && $user->companies()->where('id', $companyId)->exists()) {
            return true;
        }

        // Accountants can post if they have the permission
        if ($user->hasRole('accountant') && $user->hasPermission('accounting.journal_entries.post')) {
            return true;
        }

        return false;
    }

    /**
     * Check if the date is within the allowed posting period.
     */
    private function isDateWithinPostingPeriod(string $date, string $companyId): bool
    {
        // For now, allow any date in the past or today
        // Future entries can be posted if allowed by business rules
        $entryDate = \Carbon\Carbon::parse($date);
        $today = \Carbon\Carbon::today();

        // Allow posting for today and past dates
        // Allow posting for future dates if user has permission
        if ($entryDate->greaterThan($today)) {
            return $this->userCanPostFutureDates();
        }

        return true;
    }

    /**
     * Check if user can post future-dated entries.
     */
    private function userCanPostFutureDates(): bool
    {
        $user = auth()->user();

        // System owners and company owners can post future dates
        if ($user->hasRole(['system_owner', 'company_owner'])) {
            return true;
        }

        return false;
    }

    /**
     * Log balance change for audit trail.
     *
     * @param  mixed  $transaction
     */
    private function logBalanceChange(Account $account, float $oldBalance, float $newBalance, $transaction): void
    {
        // This could be stored in a separate account balance audit table
        // For now, we'll include it in the journal audit metadata
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
                ],
                'actor_id' => auth()->id(),
            ],
            auth()->id()
        );
    }
}
