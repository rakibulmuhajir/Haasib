<?php

namespace Modules\Accounting\Domain\JournalEntries\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\Accounting\Domain\JournalEntries\Events\BatchPosted;
use Modules\Accounting\Domain\Ledgers\Actions\UpdateLedgerBalancesAction;

class ProcessBatchPosted
{
    public function __construct(
        private UpdateLedgerBalancesAction $updateLedgerBalances
    ) {}

    public function handle(BatchPosted $event): void
    {
        Log::info('Journal batch posted', [
            'batch_id' => $event->batch->id,
            'company_id' => $event->batch->company_id,
            'batch_name' => $event->batch->name,
            'total_entries' => $event->batch->total_entries,
            'posted_by' => $event->postedBy,
            'posted_at' => $event->batch->posted_at,
            'timestamp' => now()->toISOString(),
        ]);

        // Update ledger balances for all affected accounts
        try {
            $entries = $event->batch->journalEntries()->with('transactions.account')->get();

            $affectedAccountIds = $entries
                ->flatMap(fn ($entry) => $entry->transactions)
                ->pluck('account.id')
                ->unique()
                ->filter()
                ->toArray();

            if (! empty($affectedAccountIds)) {
                $this->updateLedgerBalances->execute($event->batch->company_id, $affectedAccountIds);

                Log::info('Ledger balances updated for batch posting', [
                    'batch_id' => $event->batch->id,
                    'affected_accounts' => count($affectedAccountIds),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to update ledger balances for posted batch', [
                'batch_id' => $event->batch->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Don't throw the exception to prevent rolling back the batch posting
            // The ledger balances can be updated later via a reconciliation process
        }
    }
}
