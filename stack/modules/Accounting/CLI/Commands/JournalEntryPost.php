<?php

namespace Modules\Accounting\CLI\Commands;

use App\Console\Command;
use App\Models\JournalEntry;
use Illuminate\Console\Command as BaseCommand;

class JournalEntryPost extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'journal:post {id : Journal entry ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Post an approved journal entry to the ledger';

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

        if ($entry->status !== 'approved') {
            $this->error("Journal entry is not in approved status. Current status: {$entry->status}");

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
            ['Account ID', 'Account Name', 'Debit', 'Credit'],
            collect($entry->transactions)->map(function ($transaction) {
                return [
                    $transaction->account_id,
                    $transaction->account?->name ?? 'Unknown',
                    $transaction->debit_credit === 'debit' ? number_format($transaction->amount, 2) : '',
                    $transaction->debit_credit === 'credit' ? number_format($transaction->amount, 2) : '',
                ];
            })
        );

        $this->info("\nSummary:");
        $this->info('Total Debits: '.number_format($totalDebits, 2));
        $this->info('Total Credits: '.number_format($totalCredits, 2));
        $this->info('Balance: '.number_format($totalDebits - $totalCredits, 2));

        if (! $this->confirm('Post this journal entry to the ledger? This will update account balances.')) {
            $this->info('Operation cancelled.');

            return Command::SUCCESS;
        }

        try {
            // Dispatch command via command bus
            $result = app('command.bus')->dispatch('journal.post', [
                'journal_entry_id' => $entry->id,
                'posted_by' => 1, // System user ID or get from context
            ]);

            $this->info('✓ Journal entry posted successfully!');
            $this->info("New Status: {$entry->fresh()->status}");
            $this->info("Posted At: {$entry->fresh()->posted_at}");

            // Show updated account balances
            $this->info("\nUpdated Account Balances:");
            $balanceData = collect($entry->transactions)->map(function ($transaction) {
                $account = $transaction->account;

                return [
                    $account->code,
                    $account->name,
                    $account->normal_balance,
                    number_format($account->current_balance, 2),
                ];
            })->unique('0'); // Unique by account code

            $this->table(
                ['Account Code', 'Account Name', 'Normal Balance', 'Current Balance'],
                $balanceData
            );

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to post journal entry: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}
