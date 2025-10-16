<?php

namespace Modules\Accounting\CLI\Commands;

use App\Console\Command;
use Illuminate\Console\Command as BaseCommand;
use Modules\Accounting\Services\LedgerService;

class JournalEntryCreate extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'journal:create 
                            {--company= : Company ID}
                            {--description= : Journal entry description}
                            {--date= : Journal entry date (Y-m-d)}
                            {--type=adjustment : Journal entry type}
                            {--reference= : Reference number}
                            {--notes= : Additional notes}
                            {--currency=USD : Currency code}
                            {--interactive : Interactive mode for creating journal entries}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new manual journal entry';

    /**
     * Execute the console command.
     */
    public function handle(LedgerService $ledgerService): int
    {
        $this->info('Creating Manual Journal Entry');

        if ($this->option('interactive')) {
            return $this->createInteractive($ledgerService);
        }

        return $this->createDirect($ledgerService);
    }

    /**
     * Create journal entry interactively.
     */
    private function createInteractive(LedgerService $ledgerService): int
    {
        $data = [];

        $data['company_id'] = $this->option('company') ?? $this->ask('Company ID');
        $data['description'] = $this->option('description') ?? $this->ask('Description');
        $data['date'] = $this->option('date') ?? $this->ask('Date (Y-m-d)', date('Y-m-d'));
        $data['type'] = $this->option('type') ?? $this->choice(
            'Journal Type',
            ['sales', 'purchase', 'payment', 'receipt', 'adjustment', 'closing', 'opening'],
            'adjustment'
        );
        $data['reference'] = $this->option('reference') ?? $this->ask('Reference (optional)');
        $data['notes'] = $this->option('notes') ?? $this->ask('Notes (optional)');
        $data['currency'] = $this->option('currency') ?? $this->ask('Currency', 'USD');

        $this->info("\nAdd journal entry lines (debit/credit pairs):");
        $lines = [];

        do {
            $line = $this->askLineDetails();
            if ($line) {
                $lines[] = $line;
            }
        } while ($this->confirm('Add another line?'));

        if (count($lines) < 2) {
            $this->error('Journal entry must have at least 2 lines.');

            return Command::FAILURE;
        }

        $data['lines'] = $lines;

        return $this->processJournalEntry($ledgerService, $data);
    }

    /**
     * Create journal entry with direct options.
     */
    private function createDirect(LedgerService $ledgerService): int
    {
        $data = [
            'company_id' => $this->option('company'),
            'description' => $this->option('description'),
            'date' => $this->option('date'),
            'type' => $this->option('type'),
            'reference' => $this->option('reference'),
            'notes' => $this->option('notes'),
            'currency' => $this->option('currency'),
            'lines' => [], // Would need to be passed via JSON or file for direct mode
        ];

        if (! $data['company_id'] || ! $data['description'] || ! $data['date']) {
            $this->error('Required options: --company, --description, --date');

            return Command::FAILURE;
        }

        $this->error('Direct mode requires JSON input for lines. Use --interactive mode.');

        return Command::FAILURE;
    }

    /**
     * Ask user for line details.
     */
    private function askLineDetails(): ?array
    {
        $this->info("\n--- New Journal Line ---");

        $accountId = $this->ask('Account ID');
        if (! $accountId) {
            return null;
        }

        $debitCredit = $this->choice('Debit or Credit?', ['debit', 'credit']);
        $amount = $this->ask('Amount');
        $description = $this->ask('Line description (optional)');

        return [
            'account_id' => $accountId,
            'debit_credit' => $debitCredit,
            'amount' => (float) $amount,
            'description' => $description,
        ];
    }

    /**
     * Process and create the journal entry.
     */
    private function processJournalEntry(LedgerService $ledgerService, array $data): int
    {
        try {
            $this->info("\nValidating journal entry...");
            $validated = $ledgerService->validateManualJournalEntry($data);

            $this->info('Creating journal entry...');
            $entry = $ledgerService->createJournalEntry($validated, $validated['lines']);

            $this->info("\n✓ Journal entry created successfully!");
            $this->info("ID: {$entry->id}");
            $this->info("Description: {$entry->description}");
            $this->info("Status: {$entry->status}");
            $this->info("Date: {$entry->date}");

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

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to create journal entry: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}
