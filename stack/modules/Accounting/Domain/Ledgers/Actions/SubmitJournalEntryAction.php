<?php

namespace Modules\Accounting\Domain\Ledgers\Actions;

use App\Models\JournalAudit;
use App\Models\JournalEntry;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SubmitJournalEntryAction
{
    /**
     * Submit a draft journal entry for approval.
     *
     * @throws ValidationException
     * @throws Exception
     */
    public function execute(string $journalEntryId, ?string $submitNote = null): JournalEntry
    {
        // Validate input
        $validator = Validator::make([
            'journal_entry_id' => $journalEntryId,
            'submit_note' => $submitNote,
        ], [
            'journal_entry_id' => 'required|uuid|exists:pgsql.acct.journal_entries,id',
            'submit_note' => 'nullable|string|max:1000',
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
            $this->validateCanSubmit($journalEntry);

            // Update journal entry status
            $journalEntry->update([
                'status' => 'pending_approval',
                'approval_note' => $validated['submit_note'],
            ]);

            // Create audit record
            JournalAudit::createEvent(
                $journalEntry->id,
                'updated',
                [
                    'previous_state' => [
                        'status' => 'draft',
                    ],
                    'new_state' => [
                        'status' => 'pending_approval',
                        'approval_note' => $validated['submit_note'],
                    ],
                    'changes' => [
                        'status' => ['from' => 'draft', 'to' => 'pending_approval'],
                    ],
                    'metadata' => [
                        'action' => 'submit_for_approval',
                        'submit_note' => $validated['submit_note'],
                    ],
                ],
                auth()->id()
            );

            return $journalEntry->fresh();
        });
    }

    /**
     * Validate that the journal entry can be submitted for approval.
     *
     * @throws Exception
     */
    private function validateCanSubmit(JournalEntry $journalEntry): void
    {
        // Check if entry is in draft status
        if ($journalEntry->status !== 'draft') {
            throw new Exception('Only draft journal entries can be submitted for approval');
        }

        // Check if entry has transactions
        if ($journalEntry->transactions->count() === 0) {
            throw new Exception('Journal entry must have at least one transaction line');
        }

        // Check if entry is balanced
        if (! $journalEntry->isBalanced()) {
            throw new Exception('Journal entry must be balanced before submission');
        }

        // Check if user has permission to submit entries for this company
        if (! $this->userCanSubmitForCompany($journalEntry->company_id)) {
            throw new Exception('You do not have permission to submit journal entries for this company');
        }

        // Check if any required validations fail
        $this->validateBusinessRules($journalEntry);
    }

    /**
     * Validate business-specific rules for the journal entry.
     *
     * @throws Exception
     */
    private function validateBusinessRules(JournalEntry $journalEntry): void
    {
        // Check if entry date is within allowed period
        if (! $this->isDateWithinAllowedPeriod($journalEntry->date, $journalEntry->company_id)) {
            throw new Exception('Journal entry date is outside the allowed accounting period');
        }

        // Check if duplicate reference exists (for manual entries)
        if ($journalEntry->reference && $this->referenceExists($journalEntry->reference, $journalEntry->company_id, $journalEntry->id)) {
            throw new Exception('Journal entry reference already exists');
        }

        // Validate account permissions and status
        foreach ($journalEntry->transactions as $transaction) {
            if (! $transaction->account->active) {
                throw new Exception("Account {$transaction->account_code} is inactive");
            }

            if (! $this->userCanUseAccount($transaction->account_id)) {
                throw new Exception("You do not have permission to use account {$transaction->account_code}");
            }
        }
    }

    /**
     * Check if the user has permission to submit entries for the company.
     */
    private function userCanSubmitForCompany(string $companyId): bool
    {
        $user = auth()->user();

        // System owners can submit for any company
        if ($user->hasRole('system_owner')) {
            return true;
        }

        // Company owners can submit for their own company
        if ($user->hasRole('company_owner') && $user->companies()->where('id', $companyId)->exists()) {
            return true;
        }

        // Accountants can submit if they have the permission
        if ($user->hasRole('accountant') && $user->hasPermission('accounting.journal_entries.submit')) {
            return true;
        }

        return false;
    }

    /**
     * Check if the user has permission to use the account.
     */
    private function userCanUseAccount(string $accountId): bool
    {
        // For now, allow all authenticated users to use all accounts
        // This can be enhanced with account-level permissions later
        return true;
    }

    /**
     * Check if the date is within the allowed accounting period.
     */
    private function isDateWithinAllowedPeriod(string $date, string $companyId): bool
    {
        // For now, allow any date in the past or today
        // This can be enhanced with proper accounting period checks later
        $entryDate = \Carbon\Carbon::parse($date);
        $today = \Carbon\Carbon::today();

        return $entryDate->lessThanOrEqualTo($today);
    }

    /**
     * Check if a reference already exists for the company.
     */
    private function referenceExists(string $reference, string $companyId, string $excludeId): bool
    {
        return JournalEntry::where('reference', $reference)
            ->where('company_id', $companyId)
            ->where('id', '!=', $excludeId)
            ->exists();
    }
}
