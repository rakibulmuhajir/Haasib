<?php

namespace App\Jobs;

use App\Models\Company;
use App\Services\CreditNoteService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RetryFailedLedgerIntegrations implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 5;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public int $maxExceptions = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly Company $company
    ) {
        $this->onQueue('ledger-integrations');
    }

    /**
     * Execute the job.
     */
    public function handle(CreditNoteService $creditNoteService): void
    {
        try {
            $pendingEntries = DB::table('acct.pending_ledger_entries')
                ->where('company_id', $this->company->id)
                ->where('reference_type', 'credit_note')
                ->where('status', 'pending')
                ->orderBy('created_at', 'asc')
                ->limit(50) // Process in batches
                ->get();

            $processedCount = 0;
            $failedCount = 0;

            foreach ($pendingEntries as $pendingEntry) {
                try {
                    $creditNote = $creditNoteService->findCreditNoteByIdentifier(
                        $pendingEntry->reference_id,
                        $this->company
                    );

                    if ($creditNote && $creditNote->is_posted && ! $creditNote->journal_entry_id) {
                        // Try to sync with ledger again
                        $success = $creditNoteService->syncCreditNoteWithLedger($creditNote, auth()->user());

                        if ($success) {
                            // Update pending entry status
                            DB::table('acct.pending_ledger_entries')
                                ->where('id', $pendingEntry->id)
                                ->update([
                                    'status' => 'processed',
                                    'processed_at' => now(),
                                    'updated_at' => now(),
                                ]);

                            $processedCount++;

                            Log::info('Successfully synced pending credit note with ledger', [
                                'credit_note_id' => $creditNote->id,
                                'credit_note_number' => $creditNote->credit_note_number,
                                'pending_entry_id' => $pendingEntry->id,
                            ]);
                        } else {
                            $failedCount++;
                        }
                    } else {
                        // Mark as processed if credit note is no longer valid for integration
                        DB::table('acct.pending_ledger_entries')
                            ->where('id', $pendingEntry->id)
                            ->update([
                                'status' => 'invalid',
                                'updated_at' => now(),
                            ]);
                    }
                } catch (\Throwable $e) {
                    $failedCount++;

                    Log::error('Failed to process pending ledger integration', [
                        'pending_entry_id' => $pendingEntry->id,
                        'credit_note_id' => $pendingEntry->reference_id,
                        'error' => $e->getMessage(),
                    ]);

                    // Update the error message
                    DB::table('acct.pending_ledger_entries')
                        ->where('id', $pendingEntry->id)
                        ->update([
                            'error_message' => $e->getMessage(),
                            'attempts' => DB::raw('attempts + 1'),
                            'last_attempt_at' => now(),
                            'updated_at' => now(),
                        ]);
                }
            }

            // Log summary
            Log::info('Retry failed ledger integrations completed', [
                'company_id' => $this->company->id,
                'company_name' => $this->company->name,
                'total_processed' => $pendingEntries->count(),
                'successful' => $processedCount,
                'failed' => $failedCount,
            ]);

            // If there are still pending entries, dispatch another job
            $remainingPending = DB::table('acct.pending_ledger_entries')
                ->where('company_id', $this->company->id)
                ->where('reference_type', 'credit_note')
                ->where('status', 'pending')
                ->count();

            if ($remainingPending > 0) {
                self::dispatch($this->company)->delay(now()->addMinutes(30));
            }

        } catch (\Throwable $e) {
            Log::error('Failed to process retry failed ledger integrations job', [
                'company_id' => $this->company->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return ['ledger-integrations', 'retry', 'company:'.$this->company->id];
    }
}
