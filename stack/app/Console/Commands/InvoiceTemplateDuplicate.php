<?php

namespace App\Console\Commands;

use App\Services\InvoiceTemplateService;

class InvoiceTemplateDuplicate extends InvoiceBaseCommand
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'invoice:template:duplicate
                           {template : Template ID, name, or UUID to duplicate}
                           {name : Name for the new template}
                           {--description= : New template description}
                           {--customer= : New customer ID}
                           {--currency= : New currency}
                           {--notes= : New default notes}
                           {--terms= : New default terms}
                           {--items= : New line items (JSON string)}
                           {--activate : Create as active template}
                           {--json : Output in JSON format}
                           {--company= : Company ID (overrides current company)}
                           {--quiet : Suppress output}';

    /**
     * The console command description.
     */
    protected $description = 'Duplicate an existing invoice template with optional modifications';

    /**
     * Execute the command logic.
     */
    protected function executeCommand(): int
    {
        $input = $this->parseInput();
        $templateIdentifier = $input['template'] ?? $this->argument('template');
        $newName = $input['name'] ?? $this->argument('name');

        try {
            // Find the source template
            $sourceTemplate = $this->findTemplate($templateIdentifier);

            // Prepare modifications
            $modifications = $this->prepareModifications($input);

            // Duplicate the template
            $templateService = new InvoiceTemplateService(
                app(\App\Services\ContextService::class),
                app(\App\Services\AuthService::class)
            );

            $duplicateTemplate = $templateService->duplicateTemplate(
                $sourceTemplate,
                $newName,
                $modifications,
                auth()->user()
            );

            $this->displaySuccess('Template duplicated successfully', [
                'Source Template' => $sourceTemplate->name,
                'New Template Name' => $duplicateTemplate->name,
                'New Template ID' => $duplicateTemplate->id,
                'Customer' => $duplicateTemplate->customer?->name ?? 'General',
                'Currency' => $duplicateTemplate->currency,
                'Status' => $duplicateTemplate->is_active ? 'Active' : 'Inactive',
                'Created At' => $duplicateTemplate->created_at->format('Y-m-d H:i:s'),
            ]);

            // Show what was modified
            if (! empty($modifications)) {
                $this->line('');
                $this->info('Modifications applied:');
                foreach ($modifications as $key => $value) {
                    $this->line("  {$key}: ".$this->formatValue($value));
                }
            }

            // Log the action
            $this->logExecution('template_duplicated', [
                'source_template_id' => $sourceTemplate->id,
                'duplicate_template_id' => $duplicateTemplate->id,
                'new_name' => $newName,
                'modifications_count' => count($modifications),
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
     * Prepare modifications for the duplicate.
     */
    protected function prepareModifications(array $input): array
    {
        $modifications = [];

        if (isset($input['description'])) {
            $modifications['description'] = $input['description'];
        }

        if (isset($input['customer'])) {
            // Validate customer exists
            $customer = \App\Models\Customer::where('company_id', $this->company->id)
                ->where('id', $input['customer'])
                ->first();

            if (! $customer) {
                $this->error("Customer '{$input['customer']}' not found.");
                exit(1);
            }

            $modifications['customer_id'] = $input['customer'];
        }

        if (isset($input['currency'])) {
            $modifications['currency'] = strtoupper($input['currency']);
        }

        if (isset($input['activate'])) {
            // This will be handled by the service
        }

        // Template data modifications
        $templateDataModifications = [];

        if (isset($input['notes'])) {
            $templateDataModifications['notes'] = $input['notes'];
        }

        if (isset($input['terms'])) {
            $templateDataModifications['terms'] = $input['terms'];
        }

        if (isset($input['items'])) {
            $decoded = json_decode($input['items'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $templateDataModifications['line_items'] = $this->normalizeLineItems($decoded);
            } else {
                $this->error('Invalid JSON in items parameter.');
                exit(1);
            }
        }

        if (! empty($templateDataModifications)) {
            $modifications['template_data'] = $templateDataModifications;
        }

        return $modifications;
    }

    /**
     * Normalize line items data.
     */
    protected function normalizeLineItems(array $lineItems): array
    {
        return collect($lineItems)->map(function ($item, $index) {
            return [
                'id' => $item['id'] ?? 'item-'.uniqid(),
                'description' => $item['description'],
                'quantity' => (float) $item['quantity'],
                'unit_price' => (float) $item['unit_price'],
                'tax_rate' => (float) ($item['tax_rate'] ?? 0),
                'discount_amount' => (float) ($item['discount_amount'] ?? 0),
            ];
        })->toArray();
    }

    /**
     * Format value for display.
     */
    protected function formatValue($value): string
    {
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_null($value)) {
            return 'N/A';
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        return (string) $value;
    }

    /**
     * Get usage examples for this command.
     */
    protected function getExamples(): array
    {
        return [
            'invoice:template:duplicate "Web Design" "Web Design v2"',
            'invoice:template:duplicate TPL-001 "Monthly Services" --customer=CUST-002',
            'invoice:template:duplicate 12345 "Updated Template" --currency=EUR --activate',
            'invoice:template:duplicate "Old Template" "New Template" --notes="Updated terms and conditions"',
        ];
    }
}
