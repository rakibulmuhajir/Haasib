<?php

namespace Modules\Accounting\CLI\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Modules\Accounting\Domain\Ledgers\Actions\Recurring\CreateRecurringTemplateAction;

class JournalTemplateCreateCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'journal:template:create 
                            {name : Template name}
                            {--company-id= : Company ID (required)}
                            {--description= : Template description}
                            {--frequency=monthly : Frequency (daily,weekly,monthly,quarterly,yearly)}
                            {--interval=1 : Interval between generations}
                            {--start-date= : Start date (Y-m-d, defaults to today)}
                            {--end-date= : End date (Y-m-d, optional)}
                            {--currency=USD : Currency code}
                            {--inactive : Create as inactive}
                            {--account-ids= : Comma-separated account IDs}
                            {--amounts= : Comma-separated amounts}
                            {--descriptions= : Comma-separated line descriptions}';

    /**
     * The console command description.
     */
    protected $description = 'Create a new recurring journal template';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Creating recurring journal template...');

        try {
            $data = $this->collectTemplateData();
            $action = new CreateRecurringTemplateAction;
            $template = $action->execute($data);

            $this->info('✓ Template created successfully!');
            $this->line("Template ID: {$template->id}");
            $this->line("Name: {$template->name}");
            $this->line("Frequency: {$template->frequency}");
            $this->line("Next Generation: {$template->next_generation_date}");
            $this->line("Total Amount: {$template->currency} ".number_format($template->total_debit, 2));
            $this->line("Lines: {$template->lines->count()}");

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('✗ Failed to create template: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Collect and validate template data from arguments and options.
     */
    protected function collectTemplateData(): array
    {
        $data = [
            'name' => $this->argument('name'),
            'company_id' => $this->option('company-id') ?: $this->ask('Company ID'),
            'description' => $this->option('description'),
            'frequency' => $this->option('frequency'),
            'interval' => (int) $this->option('interval'),
            'start_date' => $this->option('start-date') ?: now()->toDateString(),
            'end_date' => $this->option('end-date'),
            'currency' => $this->option('currency'),
            'is_active' => ! $this->option('inactive'),
        ];

        // Validate basic data
        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'company_id' => 'required|uuid|exists:companies,id',
            'frequency' => 'required|in:daily,weekly,monthly,quarterly,yearly',
            'interval' => 'integer|min:1|max:999',
            'start_date' => 'required|date|before_or_equal:today',
            'end_date' => 'nullable|date|after:start_date',
            'currency' => 'string|max:3',
        ]);

        if ($validator->fails()) {
            $this->error('Validation failed:');
            foreach ($validator->errors()->all() as $error) {
                $this->line("• {$error}");
            }
            throw new \InvalidArgumentException('Invalid template data');
        }

        // Collect template lines
        $data['lines'] = $this->collectTemplateLines($data['company_id']);

        return $data;
    }

    /**
     * Collect template lines from user input.
     */
    protected function collectTemplateLines(string $companyId): array
    {
        $lines = [];
        $accountIds = $this->option('account-ids');
        $amounts = $this->option('amounts');

        if ($accountIds && $amounts) {
            // Parse comma-separated values
            $accountIdArray = explode(',', $accountIds);
            $amountArray = explode(',', $amounts);
            $descriptionsArray = $this->option('descriptions') ? explode(',', $this->option('descriptions')) : [];

            if (count($accountIdArray) !== count($amountArray)) {
                throw new \InvalidArgumentException('Number of account IDs must match number of amounts');
            }

            for ($i = 0; $i < count($accountIdArray); $i++) {
                $lines[] = [
                    'account_id' => trim($accountIdArray[$i]),
                    'debit_credit' => $i % 2 === 0 ? 'debit' : 'credit', // Alternate debits and credits
                    'amount' => (float) trim($amountArray[$i]),
                    'description' => $descriptionsArray[$i] ?? null,
                ];
            }
        } else {
            // Interactive mode
            $this->info('Enter template lines (minimum 2 lines for balanced entry):');

            do {
                $line = $this->collectTemplateLine($companyId);
                if ($line) {
                    $lines[] = $line;
                }
            } while (count($lines) < 2 || $this->confirm('Add another line?'));

            // Validate balance
            $totalDebit = collect($lines)->where('debit_credit', 'debit')->sum('amount');
            $totalCredit = collect($lines)->where('debit_credit', 'credit')->sum('amount');

            if (abs($totalDebit - $totalCredit) > 0.01) {
                $this->error("Template lines must be balanced. Total Debit: {$totalDebit}, Total Credit: {$totalCredit}");
                throw new \InvalidArgumentException('Unbalanced template lines');
            }
        }

        return $lines;
    }

    /**
     * Collect a single template line.
     */
    protected function collectTemplateLine(string $companyId): ?array
    {
        $accountId = $this->ask('Account ID');
        if (! $accountId) {
            return null;
        }

        $debitCredit = $this->choice('Debit or Credit?', ['debit', 'credit']);
        $amount = $this->ask('Amount');
        $description = $this->ask('Description (optional)');

        return [
            'account_id' => $accountId,
            'debit_credit' => $debitCredit,
            'amount' => (float) $amount,
            'description' => $description ?: null,
        ];
    }
}
