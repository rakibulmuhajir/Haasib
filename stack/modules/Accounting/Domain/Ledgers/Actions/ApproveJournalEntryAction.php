<?php

namespace Modules\Accounting\Domain\Ledgers\Actions;

use App\Models\JournalAudit;
use App\Models\JournalEntry;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ApproveJournalEntryAction
{
    /**
     * Approve a journal entry pending approval.
     *
     * @throws ValidationException
     * @throws Exception
     */
    public function execute(string $journalEntryId, ?string $approvalNote = null): JournalEntry
    {
        // Validate input
        $validator = Validator::make([
            'journal_entry_id' => $journalEntryId,
            'approval_note' => $approvalNote,
        ], [
            'journal_entry_id' => 'required|uuid|exists:pgsql.acct.journal_entries,id',
            'approval_note' => 'nullable|string|max:1000',
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
            $this->validateCanApprove($journalEntry);

            // Update journal entry
            $journalEntry->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'approval_note' => $validated['approval_note'],
            ]);

            // Create audit record
            JournalAudit::createEvent(
                $journalEntry->id,
                'approved',
                [
                    'previous_state' => [
                        'status' => 'pending_approval',
                    ],
                    'new_state' => [
                        'status' => 'approved',
                        'approved_by' => auth()->id(),
                        'approved_at' => now()->toISOString(),
                        'approval_note' => $validated['approval_note'],
                    ],
                    'changes' => [
                        'status' => ['from' => 'pending_approval', 'to' => 'approved'],
                        'approved_by' => ['from' => null, 'to' => auth()->id()],
                        'approved_at' => ['from' => null, 'to' => now()->toISOString()],
                    ],
                    'metadata' => [
                        'action' => 'approve',
                        'approval_note' => $validated['approval_note'],
                    ],
                ],
                auth()->id()
            );

            return $journalEntry->fresh();
        });
    }

    /**
     * Validate that the journal entry can be approved.
     *
     * @throws Exception
     */
    private function validateCanApprove(JournalEntry $journalEntry): void
    {
        // Check if entry is pending approval
        if ($journalEntry->status !== 'pending_approval') {
            throw new Exception('Only journal entries pending approval can be approved');
        }

        // Check if user has permission to approve entries for this company
        if (! $this->userCanApproveForCompany($journalEntry->company_id)) {
            throw new Exception('You do not have permission to approve journal entries for this company');
        }

        // Check if user is approving their own entry (business rule)
        if ($journalEntry->created_by === auth()->id() && ! $this->userCanApproveOwnEntries()) {
            throw new Exception('You cannot approve your own journal entries');
        }

        // Re-validate business rules that might have changed since submission
        $this->validateBusinessRules($journalEntry);
    }

    /**
     * Validate business-specific rules for the journal entry.
     *
     * @throws Exception
     */
    private function validateBusinessRules(JournalEntry $journalEntry): void
    {
        // Check if entry is still balanced
        if (! $journalEntry->isBalanced()) {
            throw new Exception('Journal entry must be balanced for approval');
        }

        // Check if entry date is still within allowed period
        if (! $this->isDateWithinAllowedPeriod($journalEntry->date, $journalEntry->company_id)) {
            throw new Exception('Journal entry date is outside the allowed accounting period');
        }

        // Re-check account status
        foreach ($journalEntry->transactions as $transaction) {
            if (! $transaction->account->active) {
                throw new Exception("Account {$transaction->account_code} is now inactive");
            }
        }

        // Check for potential duplicates
        if ($this->hasDuplicateApproval($journalEntry)) {
            throw new Exception('A similar journal entry is already approved');
        }
    }

    /**
     * Check if the user has permission to approve entries for the company.
     */
    private function userCanApproveForCompany(string $companyId): bool
    {
        $user = auth()->user();

        // System owners can approve for any company
        if ($user->hasRole('system_owner')) {
            return true;
        }

        // Company owners can approve for their own company
        if ($user->hasRole('company_owner') && $user->companies()->where('id', $companyId)->exists()) {
            return true;
        }

        // Accountants can approve if they have the permission
        if ($user->hasRole('accountant') && $user->hasPermission('accounting.journal_entries.approve')) {
            return true;
        }

        return false;
    }

    /**
     * Check if the user can approve their own entries.
     */
    private function userCanApproveOwnEntries(): bool
    {
        $user = auth()->user();

        // System owners can approve their own entries
        if ($user->hasRole('system_owner')) {
            return true;
        }

        // Company owners can approve their own entries
        if ($user->hasRole('company_owner')) {
            return true;
        }

        // Accountants cannot approve their own entries
        return false;
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
     * Check for potential duplicate approvals.
     */
    private function hasDuplicateApproval(JournalEntry $journalEntry): bool
    {
        // Check for approved entries with similar descriptions and dates
        return JournalEntry::where('company_id', $journalEntry->company_id)
            ->where('status', 'approved')
            ->where('date', $journalEntry->date)
            ->where('description', $journalEntry->description)
            ->where('id', '!=', $journalEntry->id)
            ->exists();
    }
}
