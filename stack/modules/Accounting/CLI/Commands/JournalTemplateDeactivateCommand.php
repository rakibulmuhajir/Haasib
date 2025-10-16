<?php

namespace Modules\Accounting\CLI\Commands;

use Illuminate\Console\Command;
use Modules\Accounting\Domain\Ledgers\Actions\Recurring\DeactivateRecurringTemplateAction;

class JournalTemplateDeactivateCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'journal:template:deactivate 
                            {template-id : Template ID to deactivate}
                            {--reason= : Reason for deactivation}
                            {--company-id= : Company ID (for bulk deactivation)}
                            {--all : Deactivate all templates for the company}';

    /**
     * The console command description.
     */
    protected $description = 'Deactivate a recurring journal template';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $action = new DeactivateRecurringTemplateAction;

            if ($this->option('all')) {
                return $this->bulkDeactivate($action);
            }

            return $this->deactivateSingle($action);

        } catch (\Exception $e) {
            $this->error('✗ Failed to deactivate template: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Deactivate a single template.
     */
    protected function deactivateSingle(DeactivateRecurringTemplateAction $action): int
    {
        $templateId = $this->argument('template-id');
        $reason = $this->option('reason') ?: $this->ask('Reason for deactivation (optional)');

        $template = \App\Models\RecurringJournalTemplate::findOrFail($templateId);

        if (! $template->is_active) {
            $this->warn("Template {$templateId} is already inactive.");

            return self::SUCCESS;
        }

        $action->execute($template, $reason);

        $this->info("✓ Template '{$template->name}' ({$templateId}) has been deactivated.");

        if ($reason) {
            $this->line("Reason: {$reason}");
        }

        return self::SUCCESS;
    }

    /**
     * Bulk deactivate templates for a company.
     */
    protected function bulkDeactivate(DeactivateRecurringTemplateAction $action): int
    {
        $companyId = $this->option('company-id') ?: $this->ask('Company ID');
        $reason = $this->option('reason') ?: $this->ask('Reason for bulk deactivation');

        if (! $this->confirm("Are you sure you want to deactivate ALL templates for company {$companyId}?")) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        $count = $action->bulkDeactivate($companyId, $reason);

        $this->info("✓ Deactivated {$count} template(s) for company {$companyId}.");

        if ($reason) {
            $this->line("Reason: {$reason}");
        }

        return self::SUCCESS;
    }
}
