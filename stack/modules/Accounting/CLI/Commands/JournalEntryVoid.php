<?php

namespace Modules\Accounting\CLI\Commands;

use App\Console\Command;
use App\Models\JournalEntry;
use Illuminate\Console\Command as BaseCommand;

class JournalEntryVoid extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'journal:void {id : Journal entry ID} {--reason= : Reason for voiding}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Void a journal entry (draft, submitted, approved, or posted)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $id = $this->argument('id');

        $entry = JournalEntry::with(['transactions.account'])->find($id);

        if (! $entry) {
            $this->error("Journal entry with ID {$id} not found.");

            return Command::FAILURE;
        }

        if (! in_array($entry->status, ['draft', 'submitted', 'approved', 'posted'])) {
            $this->error("Journal entry cannot be voided in its current status: {$entry->status}");

            return Command::FAILURE;
        }

        $this->info('Journal Entry Details:');
        $this->info("ID: {$entry->id}");
        $this->info("Description: {$entry->description}");
        $this->info("Date: {$entry->date}");
        $this->info("Type: {$entry->type}");
        $this->info("Status: {$entry->status}");

        if ($entry->posted_at) {
            $this->info("Posted At: {$entry->posted_at}");
        }

        $totalDebits = $entry->transactions->where('debit_credit', 'debit')->sum('amount');
        $totalCredits = $entry->transactions->where('debit_credit', 'credit')->sum('amount');

        $this->table(
            ['Account', 'Debit', 'Credit'],
            collect($entry->transactions)->map(function ($transaction) {
                return [
                    $transaction->account_id,
                    $transaction->debit_credit === 'debit' ? number_format($transaction->amount, 2) : '',
                    $transaction->debit_credit === 'credit' ? number_format($transaction->amount, 2) : '',
                ];
            })
        );

        $this->info("\nSummary:");
        $this->info('Total Debits: '.number_format($totalDebits, 2));
        $this->info('Total Credits: '.number_format($totalCredits, 2));

        if ($entry->status === 'posted') {
            $this->warn("\n⚠️  WARNING: This entry has been posted and has updated account balances.");
            $this->warn('Voiding it will reverse the account balance updates.');
        }

        $reason = $this->option('reason') ?? $this->ask('Reason for voiding', 'Voided by administrator');

        if (! $this->confirm('Are you sure you want to void this journal entry? This action cannot be undone.')) {
            $this->info('Operation cancelled.');

            return Command::SUCCESS;
        }

        try {
            // Dispatch command via command bus
            $result = app('command.bus')->dispatch('journal.void', [
                'journal_entry_id' => $entry->id,
                'voided_by' => 1, // System user ID or get from context
                'reason' => $reason,
            ]);

            $this->info('✓ Journal entry voided successfully!');
            $this->info("New Status: {$entry->fresh()->status}");

            if ($entry->fresh()->voided_at) {
                $this->info("Voided At: {$entry->fresh()->voided_at}");
                $this->info("Void Reason: {$reason}");
            }

            // If it was a posted entry, show account balance changes
            if ($entry->status === 'posted') {
                $this->info("\nAccount balances have been reversed.");
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to void journal entry: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}
