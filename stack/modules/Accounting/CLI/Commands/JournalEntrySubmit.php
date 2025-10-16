<?php

namespace Modules\Accounting\CLI\Commands;

use App\Console\Command;
use App\Models\JournalEntry;
use Illuminate\Console\Command as BaseCommand;

class JournalEntrySubmit extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'journal:submit {id : Journal entry ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Submit a draft journal entry for approval';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $id = $this->argument('id');

        $entry = JournalEntry::find($id);

        if (! $entry) {
            $this->error("Journal entry with ID {$id} not found.");

            return Command::FAILURE;
        }

        if ($entry->status !== 'draft') {
            $this->error("Journal entry is not in draft status. Current status: {$entry->status}");

            return Command::FAILURE;
        }

        $this->info('Journal Entry Details:');
        $this->info("ID: {$entry->id}");
        $this->info("Description: {$entry->description}");
        $this->info("Date: {$entry->date}");
        $this->info("Type: {$entry->type}");
        $this->info("Status: {$entry->status}");

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
        $this->info('Balance: '.number_format($totalDebits - $totalCredits, 2));

        if (! $this->confirm('Submit this journal entry for approval?')) {
            $this->info('Operation cancelled.');

            return Command::SUCCESS;
        }

        try {
            // Dispatch command via command bus
            $result = app('command.bus')->dispatch('journal.submit', [
                'journal_entry_id' => $entry->id,
                'submitted_by' => 1, // System user ID or get from context
            ]);

            $this->info('✓ Journal entry submitted for approval successfully!');
            $this->info("New Status: {$entry->fresh()->status}");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to submit journal entry: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}
