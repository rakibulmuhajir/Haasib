<?php

namespace App\Console\Commands;

use App\Services\InvoiceTemplateService;

class InvoiceTemplateUpdate extends InvoiceBaseCommand
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'invoice:template:update
                           {template : Template ID, name, or UUID}
                           {--name= : New template name}
                           {--description= : New template description}
                           {--customer= : New customer ID (use "none" to remove)}
                           {--currency= : New currency}
                           {--notes= : New default notes}
                           {--terms= : New default terms}
                           {--payment-terms= : New payment terms in days}
                           {--items= : New line items (JSON string)}
                           {--add-items= : Additional line items (JSON string)}
                           {--remove-items= : Item IDs to remove (comma-separated)}
                           {--activate : Activate the template}
                           {--deactivate : Deactivate the template}
                           {--json : Output in JSON format}
                           {--company= : Company ID (overrides current company)}
                           {--quiet : Suppress output}';

    /**
     * The console command description.
     */
    protected $description = 'Update an existing invoice template';

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

            // Prepare update data
            $updateData = $this->prepareUpdateData($input, $template);

            if (empty($updateData)) {
                $this->warn('No updates specified. Use --help to see available options.');

                return self::FAILURE;
            }

            // Update the template
            $templateService = new InvoiceTemplateService(
                app(\App\Services\ContextService::class),
                app(\App\Services\AuthService::class)
            );

            $updatedTemplate = $templateService->updateTemplate($template, $updateData, auth()->user());

            $this->displaySuccess('Template updated successfully', [
                'Template Name' => $updatedTemplate->name,
                'ID' => $updatedTemplate->id,
                'Status' => $updatedTemplate->is_active ? 'Active' : 'Inactive',
                'Updated At' => $updatedTemplate->updated_at->format('Y-m-d H:i:s'),
            ]);

            // Log the action
            $this->logExecution('template_updated', [
                'template_id' => $updatedTemplate->id,
                'template_name' => $updatedTemplate->name,
                'changes' => array_keys($updateData),
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
     * Prepare update data from input.
     */
    protected function prepareUpdateData(array $input, $template): array
    {
        $updateData = [];

        // Basic fields
        if (isset($input['name'])) {
            $updateData['name'] = $input['name'];
        }

        if (isset($input['description'])) {
            $updateData['description'] = $input['description'];
        }

        if (isset($input['customer'])) {
            if ($input['customer'] === 'none') {
                $updateData['customer_id'] = null;
            } else {
                // Validate customer exists
                $customer = \App\Models\Customer::where('company_id', $this->company->id)
                    ->where('id', $input['customer'])
                    ->first();

                if (! $customer) {
                    $this->error("Customer '{$input['customer']}' not found.");
                    exit(1);
                }

                $updateData['customer_id'] = $input['customer'];
            }
        }

        if (isset($input['currency'])) {
            $updateData['currency'] = strtoupper($input['currency']);
        }

        if (isset($input['activate'])) {
            $updateData['is_active'] = true;
        }

        if (isset($input['deactivate'])) {
            $updateData['is_active'] = false;
        }

        // Template data updates
        $templateData = $template->template_data ?? [];
        $templateDataUpdated = false;

        if (isset($input['notes'])) {
            $templateData['notes'] = $input['notes'];
            $templateDataUpdated = true;
        }

        if (isset($input['terms'])) {
            $templateData['terms'] = $input['terms'];
            $templateDataUpdated = true;
        }

        if (isset($input['payment_terms'])) {
            $templateData['payment_terms'] = (int) $input['payment_terms'];
            $templateDataUpdated = true;
        }

        // Handle line items updates
        if (isset($input['items'])) {
            $decoded = json_decode($input['items'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $templateData['line_items'] = $this->normalizeLineItems($decoded);
                $templateDataUpdated = true;
            } else {
                $this->error('Invalid JSON in items parameter.');
                exit(1);
            }
        }

        // Add additional items
        if (isset($input['add_items'])) {
            $decoded = json_decode($input['add_items'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $currentItems = $templateData['line_items'] ?? [];
                $newItems = $this->normalizeLineItems($decoded);
                $templateData['line_items'] = array_merge($currentItems, $newItems);
                $templateDataUpdated = true;
            } else {
                $this->error('Invalid JSON in add-items parameter.');
                exit(1);
            }
        }

        // Remove items
        if (isset($input['remove_items'])) {
            $itemIdsToRemove = explode(',', $input['remove_items']);
            $itemIdsToRemove = array_map('trim', $itemIdsToRemove);

            $currentItems = $templateData['line_items'] ?? [];
            $templateData['line_items'] = array_filter($currentItems, function ($item) use ($itemIdsToRemove) {
                return ! in_array($item['id'] ?? '', $itemIdsToRemove);
            });
            $templateData['line_items'] = array_values($templateData['line_items']);
            $templateDataUpdated = true;
        }

        if ($templateDataUpdated) {
            $updateData['template_data'] = $templateData;
        }

        return $updateData;
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
     * Get usage examples for this command.
     */
    protected function getExamples(): array
    {
        return [
            'invoice:template:update "Web Design" --name="Updated Web Design"',
            'invoice:template:update TPL-001 --customer=CUST-002',
            'invoice:template:update 12345 --deactivate',
            'invoice:template:update "Services" --notes="Updated terms and conditions"',
            'invoice:template:update template-name --add-items=\'[{"description":"New Service","quantity":1,"unit_price":200}]\'',
        ];
    }
}
