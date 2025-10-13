<?php

namespace App\Console\Commands;

use App\Services\InvoiceTemplateService;

class InvoiceTemplateDelete extends InvoiceBaseCommand
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'invoice:template:delete
                           {template : Template ID, name, or UUID}
                           {--force : Force deletion without confirmation}
                           {--json : Output in JSON format}
                           {--company= : Company ID (overrides current company)}
                           {--quiet : Suppress output}';

    /**
     * The console command description.
     */
    protected $description = 'Delete an invoice template';

    /**
     * Execute the command logic.
     */
    protected function executeCommand(): int
    {
        $input = $this->parseInput();
        $templateIdentifier = $input['template'] ?? $this->argument('template');

        try {
            // Find the template
            $template = $this->findTemplate($templateIdentifier);

            // Show template details before deletion
            $this->displayTemplateInfo($template);

            // Confirm deletion unless force flag is used
            if (! isset($input['force'])) {
                if (! $this->confirm("Are you sure you want to delete template '{$template->name}'? This action cannot be undone.")) {
                    $this->info('Template deletion cancelled.');

                    return self::SUCCESS;
                }
            }

            // Delete the template
            $templateService = new InvoiceTemplateService(
                app(\App\Services\ContextService::class),
                app(\App\Services\AuthService::class)
            );

            $templateService->deleteTemplate($template, auth()->user());

            $this->displaySuccess('Template deleted successfully', [
                'Template Name' => $template->name,
                'ID' => $template->id,
                'Deleted At' => now()->format('Y-m-d H:i:s'),
            ]);

            // Log the action
            $this->logExecution('template_deleted', [
                'template_id' => $template->id,
                'template_name' => $template->name,
                'forced' => isset($input['force']),
            ]);

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->handleServiceException($exception);

            return self::FAILURE;
        }
    }

    /**
     * Find template by ID, name, or UUID.
     */
    protected function findTemplate(string $identifier)
    {
        // Try by UUID
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $identifier)) {
            $template = \App\Models\InvoiceTemplate::where('id', $identifier)
                ->where('company_id', $this->company->id)
                ->first();
        }

        // Try by name (exact match)
        if (! isset($template)) {
            $template = \App\Models\InvoiceTemplate::where('name', $identifier)
                ->where('company_id', $this->company->id)
                ->first();
        }

        // Try by partial name match
        if (! isset($template)) {
            $template = \App\Models\InvoiceTemplate::where('name', 'ilike', "%{$identifier}%")
                ->where('company_id', $this->company->id)
                ->first();
        }

        if (! $template) {
            $this->error("Template '{$identifier}' not found.");
            exit(1);
        }

        return $template;
    }

    /**
     * Display template information before deletion.
     */
    protected function displayTemplateInfo($template): void
    {
        $this->info('Template to be deleted:');
        $this->line(str_repeat('=', 50));
        $this->line("Name: {$template->name}");
        $this->line("ID: {$template->id}");
        $this->line('Description: '.($template->description ?? 'N/A'));
        $this->line('Customer: '.($template->customer?->name ?? 'General'));
        $this->line("Currency: {$template->currency}");
        $this->line('Status: '.($template->is_active ? 'Active' : 'Inactive'));

        $templateData = $template->template_data ?? [];
        $lineItemsCount = count($templateData['line_items'] ?? []);
        $this->line("Line Items: {$lineItemsCount}");

        $this->line('Created: '.$template->created_at->format('Y-m-d H:i:s'));
        $this->line('Updated: '.$template->updated_at->format('Y-m-d H:i:s'));
        $this->line('');
    }

    /**
     * Get usage examples for this command.
     */
    protected function getExamples(): array
    {
        return [
            'invoice:template:delete "Old Template"',
            'invoice:template:delete TPL-001 --force',
            'invoice:template:delete 12345',
        ];
    }
}
