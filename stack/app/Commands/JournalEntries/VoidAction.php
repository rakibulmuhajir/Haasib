<?php

namespace App\Commands\JournalEntries;

use App\Commands\BaseCommand;
use App\Services\ServiceContext;
use App\Models\JournalEntry;
use App\Models\Account;
use Illuminate\Support\Facades\DB;
use Exception;

class VoidAction extends BaseCommand
{
    public function handle(): JournalEntry
    {
        return $this->executeInTransaction(function () {
            $companyId = $this->context->getCompanyId();
            $journalEntryId = $this->getValue('id');
            $userId = $this->context->getUserId();
            
            if (!$companyId || !$journalEntryId || !$userId) {
                throw new Exception('Invalid service context: missing company, user, or journal entry ID');
            }

            // Find and validate journal entry
            $journalEntry = JournalEntry::where('id', $journalEntryId)
                ->where('company_id', $companyId)
                ->where('status', 'posted')
                ->with(['journalLines.account'])
                ->firstOrFail();

            // Reverse account balances
            foreach ($journalEntry->journalLines as $line) {
                $account = $line->account;
                $balanceChange = $line->debit - $line->credit;

                // Apply normal balance logic (reverse for void)
                if ($account->normal_balance === 'credit') {
                    $balanceChange = -$balanceChange;
                }

                // Subtract the original change (reverse it)
                $account->decrement('current_balance', $balanceChange);
                $account->touch('last_updated_at');

                // Log account balance reversal
                $this->audit('account.balance_reversed', [
                    'account_id' => $account->id,
                    'account_code' => $account->code,
                    'account_name' => $account->name,
                    'balance_change' => -$balanceChange, // Negative because we're reversing
                    'new_balance' => $account->current_balance,
                    'journal_entry_id' => $journalEntry->id,
                    'reason' => $this->getValue('reason', 'Journal entry voided'),
                ]);
            }

            // Update journal entry status
            $journalEntry->update([
                'status' => 'voided',
                'voided_at' => now(),
                'voided_by' => $userId,
                'void_reason' => $this->getValue('reason'),
            ]);

            $this->audit('journal_entry.voided', [
                'journal_entry_id' => $journalEntry->id,
                'reference' => $journalEntry->reference,
                'total_debits' => $journalEntry->total_debits,
                'total_credits' => $journalEntry->total_credits,
                'voided_by' => $userId,
                'void_reason' => $this->getValue('reason'),
            ]);

            return $journalEntry->load(['journalLines.account', 'batch', 'createdBy', 'postedBy', 'voidedBy']);
        });
    }
}