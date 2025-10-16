<?php

namespace Modules\Accounting\CLI\Commands;

use Illuminate\Console\Command;
use Modules\Accounting\Domain\Ledgers\Actions\Recurring\GenerateJournalEntriesFromTemplateAction;

class JournalTemplateGenerateCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'journal:template:generate 
                            {template-id : Template ID to generate entry from}
                            {--preview : Preview what would be generated without actually creating}
                            {--force : Force generation even if not due}';

    /**
     * The console command description.
     */
    protected $description = 'Generate a journal entry from a recurring template';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $templateId = $this->argument('template-id');
            $template = \App\Models\RecurringJournalTemplate::with(['lines.account'])
                ->findOrFail($templateId);

            if ($this->option('preview')) {
                return $this->previewGeneration($template);
            }

            return $this->generateEntry($template);

        } catch (\Exception $e) {
            $this->error('✗ Failed to generate entry: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Preview what would be generated.
     */
    protected function previewGeneration($template): int
    {
        $this->info("Previewing journal entry generation for template: {$template->name}");
        $this->newLine();

        $this->line('Template Details:');
        $this->line("• ID: {$template->id}");
        $this->line("• Name: {$template->name}");
        $this->line("• Frequency: {$template->frequency}");
        $this->line("• Next Generation Date: {$template->next_generation_date}");
        $this->line('• Active: '.($template->is_active ? 'Yes' : 'No'));
        $this->line("• Total Amount: {$template->currency} ".number_format($template->total_debit, 2));

        $this->newLine();
        $this->line('Projected Journal Entry:');
        $this->line("• Description: Auto-generated: {$template->name} (".now()->format('Y-m-d').')');
        $this->line('• Reference: REC-'.str_pad($template->id, 6, '0', STR_PAD_LEFT).'-'.now()->format('Ymd'));
        $this->line('• Date: '.now()->toDateString());
        $this->line('• Status: draft');

        $this->newLine();
        $this->line('Projected Transactions:');

        foreach ($template->lines as $index => $line) {
            $account = $line->account;
            $this->line(sprintf(
                '  %d. %s (%s) - %s %s - %s',
                $index + 1,
                $account->name,
                $account->code,
                $line->debit_credit,
                number_format($line->amount, 2),
                $line->description ?? 'No description'
            ));
        }

        $isDue = $template->next_generation_date <= now()->toDateString() && $template->is_active;
        $this->newLine();

        if ($isDue) {
            $this->info('✓ This template IS due for generation.');
        } else {
            $this->warn('⊘ This template is NOT due for generation.');
            if (! $template->is_active) {
                $this->line('  Reason: Template is inactive');
            } else {
                $this->line("  Reason: Next generation date is {$template->next_generation_date}");
            }
        }

        if (! $this->option('force') && ! $isDue) {
            $this->newLine();
            if ($this->confirm('Generate anyway? (This would require --force flag)')) {
                $this->comment('Use the --force flag to generate even when not due.');
            }
        }

        return self::SUCCESS;
    }

    /**
     * Generate the journal entry.
     */
    protected function generateEntry($template): int
    {
        $this->info("Generating journal entry from template: {$template->name}");

        if (! $this->option('force')) {
            $isDue = $template->next_generation_date <= now()->toDateString() && $template->is_active;

            if (! $isDue) {
                if (! $template->is_active) {
                    $this->warn('Template is inactive. Use --force to generate anyway.');
                } else {
                    $this->warn("Template is not due for generation (next date: {$template->next_generation_date}). Use --force to generate anyway.");
                }

                if (! $this->confirm('Continue with generation?')) {
                    $this->info('Generation cancelled.');

                    return self::SUCCESS;
                }
            }
        }

        $action = new GenerateJournalEntriesFromTemplateAction;
        $journalEntry = $action->execute($template);

        if (! $journalEntry) {
            $this->warn('No journal entry was generated. Template may not be due for generation.');

            return self::SUCCESS;
        }

        $this->info('✓ Journal entry generated successfully!');
        $this->newLine();

        $this->line('Journal Entry Details:');
        $this->line("• ID: {$journalEntry->id}");
        $this->line("• Description: {$journalEntry->description}");
        $this->line("• Reference: {$journalEntry->reference}");
        $this->line("• Date: {$journalEntry->date}");
        $this->line("• Status: {$journalEntry->status}");
        $this->line("• Currency: {$journalEntry->currency}");
        $this->line("• Transactions: {$journalEntry->transactions->count()}");

        $this->newLine();
        $this->line('Transactions:');

        foreach ($journalEntry->transactions as $index => $transaction) {
            $account = $transaction->account;
            $this->line(sprintf(
                '  %d. %s (%s) - %s %s',
                $index + 1,
                $account->name,
                $account->code,
                $transaction->debit_credit,
                number_format($transaction->amount, 2)
            ));
        }

        $this->newLine();
        $this->info("Template's next generation date updated to: {$template->fresh()->next_generation_date}");

        return self::SUCCESS;
    }
}
