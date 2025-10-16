<?php

namespace Modules\Accounting\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Accounting\Jobs\GenerateRecurringJournalEntries;

class GenerateRecurringJournalEntriesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'accounting:generate-recurring-journals 
                            {--force : Force generation even if not scheduled}
                            {--template-id= : Generate for specific template ID}
                            {--company-id= : Generate for specific company ID}
                            {--queue : Dispatch to queue instead of running immediately}
                            {--preview : Preview what would be generated without actually generating}';

    /**
     * The console command description.
     */
    protected $description = 'Generate recurring journal entries from active templates';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting recurring journal entries generation...');

        $startTime = now();

        try {
            if ($this->option('queue')) {
                return $this->dispatchToQueue();
            }

            return $this->executeImmediately();

        } catch (\Exception $e) {
            $this->error('Failed to generate recurring journal entries: '.$e->getMessage());
            Log::error('Recurring journal entries command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'options' => $this->options(),
            ]);

            return self::FAILURE;
        } finally {
            $duration = $startTime->diffInSeconds(now());
            $this->info("Command completed in {$duration} seconds.");
        }
    }

    /**
     * Execute the generation immediately.
     */
    protected function executeImmediately(): int
    {
        $action = new \Modules\Accounting\Domain\Ledgers\Actions\Recurring\GenerateJournalEntriesFromTemplateAction;

        if ($this->option('preview')) {
            return $this->previewGeneration($action);
        }

        if ($this->option('template-id')) {
            return $this->generateForTemplate($action, $this->option('template-id'));
        }

        if ($this->option('company-id')) {
            return $this->generateForCompany($action, $this->option('company-id'));
        }

        return $this->generateForAllTemplates($action);
    }

    /**
     * Dispatch the job to queue.
     */
    protected function dispatchToQueue(): int
    {
        $this->info('Dispatching recurring journal entries generation job to queue...');

        $job = new GenerateRecurringJournalEntries;
        $job->dispatch();

        $this->info('Job dispatched successfully to accounting queue.');

        return self::SUCCESS;
    }

    /**
     * Preview what would be generated without actually generating.
     */
    protected function previewGeneration($action): int
    {
        $this->info('Previewing recurring journal entries generation...');

        // Get templates that would be processed
        $templates = \App\Models\RecurringJournalTemplate::query()
            ->where('is_active', true)
            ->where('next_generation_date', '<=', now()->toDateString())
            ->when($this->option('template-id'), function ($query, $templateId) {
                return $query->where('id', $templateId);
            })
            ->when($this->option('company-id'), function ($query, $companyId) {
                return $query->where('company_id', $companyId);
            })
            ->with(['lines.account'])
            ->get();

        if ($templates->isEmpty()) {
            $this->info('No templates are due for generation.');

            return self::SUCCESS;
        }

        $this->info("Found {$templates->count()} template(s) due for generation:");
        $this->newLine();

        foreach ($templates as $template) {
            $this->line("• {$template->name} (ID: {$template->id})");
            $this->line("  Company: {$template->company_id}");
            $this->line("  Frequency: {$template->frequency}");
            $this->line("  Next Generation: {$template->next_generation_date}");
            $this->line("  Amount: {$template->currency} ".number_format($template->total_debit, 2));
            $this->line("  Lines: {$template->lines->count()}");
            $this->newLine();
        }

        $this->comment('Use --preview flag to see what would be generated without actually creating entries.');

        return self::SUCCESS;
    }

    /**
     * Generate for a specific template.
     */
    protected function generateForTemplate($action, string $templateId): int
    {
        $template = \App\Models\RecurringJournalTemplate::findOrFail($templateId);

        $this->info("Generating entries for template: {$template->name}");

        $entry = $action->execute($template);

        if ($entry) {
            $this->info("✓ Generated journal entry ID: {$entry->id}");

            return self::SUCCESS;
        } else {
            $this->info('✗ Template not due for generation');

            return self::SUCCESS;
        }
    }

    /**
     * Generate for a specific company.
     */
    protected function generateForCompany($action, string $companyId): int
    {
        $this->info("Generating entries for company: {$companyId}");

        $templates = \App\Models\RecurringJournalTemplate::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('next_generation_date', '<=', now()->toDateString())
            ->get();

        if ($templates->isEmpty()) {
            $this->info('No templates are due for generation for this company.');

            return self::SUCCESS;
        }

        $generatedCount = 0;
        foreach ($templates as $template) {
            $entry = $action->execute($template);
            if ($entry) {
                $this->info("✓ Generated entry for template: {$template->name} (Entry ID: {$entry->id})");
                $generatedCount++;
            }
        }

        $this->info("Generated {$generatedCount} journal entries for company {$companyId}");

        return self::SUCCESS;
    }

    /**
     * Generate for all due templates.
     */
    protected function generateForAllTemplates($action): int
    {
        $this->info('Generating entries for all due templates...');

        $results = $action->generateForAllDueTemplates();

        $successful = count(array_filter($results, fn ($r) => $r['status'] === 'success'));
        $skipped = count(array_filter($results, fn ($r) => $r['status'] === 'skipped'));
        $errors = count(array_filter($results, fn ($r) => $r['status'] === 'error'));

        $this->info('Results:');
        $this->line("✓ Successful: {$successful}");
        $this->line("⊘ Skipped: {$skipped}");
        $this->line("✗ Errors: {$errors}");

        if ($errors > 0) {
            $this->newLine();
            $this->warn('Errors encountered:');
            foreach ($results as $result) {
                if ($result['status'] === 'error') {
                    $this->line("• {$result['template_name']}: {$result['message']}");
                }
            }
        }

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
