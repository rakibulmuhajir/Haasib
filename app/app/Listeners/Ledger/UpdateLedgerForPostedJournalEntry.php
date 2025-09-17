<?php

namespace App\Listeners\Ledger;

use App\Events\Ledger\JournalEntryPosted;
use App\Models\LedgerAccount;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateLedgerForPostedJournalEntry implements ShouldQueue
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(JournalEntryPosted $event): void
    {
        $journalEntry = $event->journalEntry;

        try {
            DB::transaction(function () use ($journalEntry) {
                // Update ledger account balances for each journal line
                foreach ($journalEntry->journalLines as $line) {
                    $account = LedgerAccount::findOrFail($line->account_id);

                    // Update account balance based on debit/credit
                    if ($line->debit_amount > 0) {
                        $account->debit_balance += $line->debit_amount;
                    } else {
                        $account->credit_balance += $line->credit_amount;
                    }

                    // Update last activity timestamp
                    $account->last_activity_at = now();
                    $account->save();

                    Log::debug('Ledger account updated', [
                        'account_id' => $account->id,
                        'account_number' => $account->account_number,
                        'debit_amount' => $line->debit_amount,
                        'credit_amount' => $line->credit_amount,
                        'new_balance' => $account->getCurrentBalance(),
                    ]);
                }

                Log::info('Ledger updated for posted journal entry', [
                    'journal_entry_id' => $journalEntry->id,
                    'lines_count' => $journalEntry->journalLines->count(),
                ]);
            });

        } catch (\Exception $e) {
            Log::error('Failed to update ledger for posted journal entry', [
                'journal_entry_id' => $journalEntry->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
