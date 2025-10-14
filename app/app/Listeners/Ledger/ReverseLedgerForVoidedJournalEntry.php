<?php

namespace App\Listeners\Ledger;

use App\Events\Ledger\JournalEntryVoided;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReverseLedgerForVoidedJournalEntry implements ShouldQueue
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
    public function handle(JournalEntryVoided $event): void
    {
        $journalEntry = $event->journalEntry;

        try {
            DB::transaction(function () use ($journalEntry) {
                // Reverse the effect on ledger account balances
                foreach ($journalEntry->journalLines as $line) {
                    $account = LedgerAccount::findOrFail($line->account_id);

                    // Reverse the debit/credit (opposite of original)
                    if ($line->debit_amount > 0) {
                        $account->debit_balance -= $line->debit_amount;
                    } else {
                        $account->credit_balance -= $line->credit_amount;
                    }

                    // Update last activity timestamp
                    $account->last_activity_at = now();
                    $account->save();

                    Log::debug('Ledger account reversed for voided entry', [
                        'account_id' => $account->id,
                        'account_number' => $account->account_number,
                        'debit_amount' => -$line->debit_amount,
                        'credit_amount' => -$line->credit_amount,
                        'new_balance' => $account->getCurrentBalance(),
                    ]);
                }

                // Create a reversing journal entry if not already created
                if (! isset($event->context['reversing_entry_id']) || ! $event->context['reversing_entry_id']) {
                    $reversingEntry = $this->createReversingEntry($journalEntry);

                    // Update the original entry's metadata with the reversing entry ID
                    $metadata = $journalEntry->metadata ?? [];
                    $metadata['reversing_entry_id'] = $reversingEntry->id;
                    $metadata['reversed_at'] = now()->toISOString();
                    $journalEntry->metadata = $metadata;
                    $journalEntry->save();

                    Log::info('Reversing journal entry created', [
                        'original_entry_id' => $journalEntry->id,
                        'reversing_entry_id' => $reversingEntry->id,
                    ]);
                }

                Log::info('Ledger reversed for voided journal entry', [
                    'journal_entry_id' => $journalEntry->id,
                    'lines_count' => $journalEntry->journalLines->count(),
                ]);
            });

        } catch (\Exception $e) {
            Log::error('Failed to reverse ledger for voided journal entry', [
                'journal_entry_id' => $journalEntry->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Create a reversing journal entry.
     */
    protected function createReversingEntry(JournalEntry $originalEntry): JournalEntry
    {
        $reversingLines = [];

        foreach ($originalEntry->journalLines as $line) {
            $reversingLines[] = [
                'account_id' => $line->account_id,
                'debit_amount' => $line->credit_amount, // Swap debit and credit
                'credit_amount' => $line->debit_amount,
                'description' => "Reversal of: {$line->description}",
                'entity_type' => $line->entity_type,
                'entity_id' => $line->entity_id,
            ];
        }

        $reversingEntry = new JournalEntry([
            'company_id' => $originalEntry->company_id,
            'description' => "Reversal of Journal Entry #{$originalEntry->reference}",
            'reference' => "REV-{$originalEntry->reference}",
            'entry_date' => now()->toDateString(),
            'status' => 'posted',
            'posted_at' => now(),
            'posted_by_user_id' => auth()->id(),
            'metadata' => [
                'original_entry_id' => $originalEntry->id,
                'reversal_reason' => 'Automatic reversal for voided entry',
                'created_at' => now()->toISOString(),
            ],
        ]);

        $reversingEntry->save();

        // Create the journal lines
        foreach ($reversingLines as $line) {
            $reversingEntry->journalLines()->create($line);
        }

        return $reversingEntry;
    }
}
