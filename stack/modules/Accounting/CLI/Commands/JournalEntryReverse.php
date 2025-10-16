<?php

namespace Modules\Accounting\CLI\Commands;

use App\Console\Command;
use App\Models\JournalEntry;
use Illuminate\Console\Command as BaseCommand;
use Modules\Accounting\Services\LedgerService;

class JournalEntryReverse extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'journal:reverse 
                            {id : Journal entry ID to reverse}
                            {--date= : Reversal date (Y-m-d, defaults to today)}
                            {--reason= : Reason for reversal}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a reversal journal entry for an existing posted entry';

    /**
     * Execute the console command.
     */
    public function handle(LedgerService $ledgerService): int
    {
        $id = $this->argument('id');

        $entry = JournalEntry::with(['transactions.account', 'reversalOf', 'reversedBy'])->find($id);

        if (! $entry) {
            $this->error("Journal entry with ID {$id} not found.");

            return Command::FAILURE;
        }

        if ($entry->status !== 'posted') {
            $this->error("Only posted journal entries can be reversed. Current status: {$entry->status}");

            return Command::FAILURE;
        }

        if ($entry->reversal_of_id) {
            $this->error('This entry is already a reversal and cannot be reversed again.');

            return Command::FAILURE;
        }

        // Check if already reversed
        $existingReversal = JournalEntry::where('reversal_of_id', $entry->id)->first();
        if ($existingReversal) {
            $this->error("This entry has already been reversed by entry #{$existingReversal->id}.");

            return Command::FAILURE;
        }

        $this->info('Original Journal Entry:');
        $this->info("ID: {$entry->id}");
        $this->info("Description: {$entry->description}");
        $this->info("Date: {$entry->date}");
        $this->info("Type: {$entry->type}");
        $this->info("Status: {$entry->status}");
        $this->info('Reference: '.($entry->reference ?? '-'));

        $totalDebits = $entry->transactions->where('debit_credit', 'debit')->sum('amount');
        $totalCredits = $entry->transactions->where('debit_credit', 'credit')->sum('amount');

        $this->table(
            ['Account', 'Debit', 'Credit', 'Description'],
            collect($entry->transactions)->map(function ($transaction) {
                return [
                    $transaction->account_id,
                    $transaction->debit_credit === 'debit' ? number_format($transaction->amount, 2) : '',
                    $transaction->debit_credit === 'credit' ? number_format($transaction->amount, 2) : '',
                    $transaction->description,
                ];
            })
        );

        $this->info("\nSummary:");
        $this->info('Total Debits: '.number_format($totalDebits, 2));
        $this->info('Total Credits: '.number_format($totalCredits, 2));

        // Get reversal details
        $reversalDate = $this->option('date') ?? $this->ask('Reversal date (Y-m-d)', date('Y-m-d'));
        $reason = $this->option('reason') ?? $this->ask('Reason for reversal');

        if (! $reversalDate) {
            $this->error('Reversal date is required.');

            return Command::FAILURE;
        }

        // Show preview of reversal
        $this->info("\nReversal Entry Preview:");
        $this->info("Date: {$reversalDate}");
        $this->info("Description: Reversal of: {$entry->description}");
        $this->info("Reason: {$reason}");
        $this->info('Reference: REV-'.($entry->reference ?? 'N/A'));

        $this->table(
            ['Account', 'Debit', 'Credit', 'Description'],
            collect($entry->transactions)->map(function ($transaction) use ($entry) {
                return [
                    $transaction->account_id,
                    $transaction->debit_credit === 'credit' ? number_format($transaction->amount, 2) : '', // Swapped
                    $transaction->debit_credit === 'debit' ? number_format($transaction->amount, 2) : '',  // Swapped
                    'Reversal: '.($transaction->description ?? $entry->description),
                ];
            })
        );

        if (! $this->confirm('Create this reversal entry?')) {
            $this->info('Operation cancelled.');

            return Command::SUCCESS;
        }

        try {
            $reversalEntry = $ledgerService->createReversalEntry(
                $entry,
                $reversalDate,
                $reason
            );

            $this->info('✓ Reversal journal entry created successfully!');
            $this->info("Reversal ID: {$reversalEntry->id}");
            $this->info("Reversal Status: {$reversalEntry->status}");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to create reversal entry: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}
