<?php

namespace App\Commands\JournalEntries;

use App\Commands\BaseCommand;
use App\Services\ServiceContext;
use App\Models\JournalEntry;
use App\Models\Account;
use Illuminate\Support\Facades\DB;
use Exception;

class PostAction extends BaseCommand
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
                ->where('status', 'draft')
                ->with(['journalLines.account'])
                ->firstOrFail();

            // Update account balances
            foreach ($journalEntry->journalLines as $line) {
                $account = $line->account;
                $balanceChange = $line->debit - $line->credit;

                // Apply normal balance logic
                if ($account->normal_balance === 'credit') {
                    $balanceChange = -$balanceChange;
                }

                $account->increment('current_balance', $balanceChange);
                $account->touch('last_updated_at');

                // Log account balance change
                $this->audit('account.balance_updated', [
                    'account_id' => $account->id,
                    'account_code' => $account->code,
                    'account_name' => $account->name,
                    'balance_change' => $balanceChange,
                    'new_balance' => $account->current_balance,
                    'journal_entry_id' => $journalEntry->id,
                ]);
            }

            // Update journal entry status
            $journalEntry->update([
                'status' => 'posted',
                'posted_at' => now(),
                'posted_by' => $userId,
            ]);

            $this->audit('journal_entry.posted', [
                'journal_entry_id' => $journalEntry->id,
                'reference' => $journalEntry->reference,
                'total_debits' => $journalEntry->total_debits,
                'total_credits' => $journalEntry->total_credits,
                'posted_by' => $userId,
            ]);

            return $journalEntry->load(['journalLines.account', 'batch', 'createdBy', 'postedBy']);
        });
    }
}