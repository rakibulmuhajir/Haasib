<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Services\InvoiceTemplateService;

class InvoiceTemplateCreate extends InvoiceBaseCommand
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'invoice:template:create
                           {name : Template name}
                           {--customer= : Customer ID (optional, for customer-specific templates)}
                           {--from-invoice= : Create template from existing invoice}
                           {--description= : Template description}
                           {--currency= : Template currency (defaults to company currency)}
                           {--notes= : Default notes for invoices}
                           {--terms= : Default terms for invoices}
                           {--payment-terms=30 : Default payment terms in days}
                           {--items= : JSON string of line items or comma-separated descriptions}
                           {--interactive : Interactive mode for line items}
                           {--json : Output in JSON format}
                           {--quiet : Suppress output}
                           {--company= : Company ID (overrides current company)}
                           {--natural= : Natural language input}
                           {--format=table : Output format (table, json, csv, text)}';

    /**
     * The console command description.
     */
    protected $description = 'Create a new invoice template';

    /**
     * Execute the command logic.
     */
    protected function executeCommand(): int
    {
        $input = $this->parseInput();

        // Handle natural language input
        if (isset($input['natural'])) {
            $input = $this->parseNaturalLanguageTemplateInput($input['natural']);
        }

        // Validate required fields
        if (empty($input['name'])) {
            $this->error('Template name is required');

            return self::FAILURE;
        }

        try {
            // Handle template creation from invoice
            if (isset($input['from_invoice'])) {
                return $this->createTemplateFromInvoice($input);
            }

            // Handle manual template creation
            return $this->createTemplateManually($input);
        } catch (\Throwable $exception) {
            $this->handleServiceException($exception);

            return self::FAILURE;
        }
    }

    /**
     * Create template from existing invoice.
     */
    protected function createTemplateFromInvoice(array $input): int
    {
        $invoice = $this->findInvoice($input['from_invoice']);
        $this->loadInvoiceRelationships($invoice, $input);

        $templateService = new InvoiceTemplateService(
            app(\App\Services\ContextService::class),
            app(\App\Services\AuthService::class)
        );

        $template = $templateService->createTemplateFromInvoice(
            $invoice,
            $input['name'],
            $input['description'] ?? null,
            auth()->user()
        );

        $this->displaySuccess('Template created from invoice', [
            'Template Name' => $template->name,
            'Source Invoice' => $invoice->invoice_number,
            'Customer' => $template->customer?->name ?? 'General',
            'Currency' => $template->currency,
            'Line Items' => count($template->template_data['line_items'] ?? []),
            'Created At' => $template->created_at->format('Y-m-d H:i:s'),
        ]);

        // Log the action
        $this->logExecution('template_created_from_invoice', [
            'template_id' => $template->id,
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
        ]);

        return self::SUCCESS;
    }

    /**
     * Create template manually.
     */
    protected function createTemplateManually(array $input): int
    {
        $templateService = new InvoiceTemplateService(
            app(\App\Services\ContextService::class),
            app(\App\Services\AuthService::class)
        );

        // Prepare template data
        $templateData = $this->prepareTemplateData($input);

        // Get customer if specified
        $customer = null;
        if (! empty($input['customer'])) {
            $customer = Customer::where('company_id', $this->company->id)
                ->where('id', $input['customer'])
                ->first();

            if (! $customer) {
                $this->error("Customer '{$input['customer']}' not found");

                return self::FAILURE;
            }
        }

        // Create the template
        $template = $templateService->createTemplate(
            $this->company,
            array_merge($templateData, [
                'name' => $input['name'],
                'description' => $input['description'] ?? null,
                'customer_id' => $customer?->id,
                'currency' => $input['currency'] ?? $this->company->base_currency,
            ]),
            auth()->user()
        );

        $this->displaySuccess('Template created successfully', [
            'Template Name' => $template->name,
            'Description' => $template->description ?? 'N/A',
            'Customer' => $template->customer?->name ?? 'General',
            'Currency' => $template->currency,
            'Line Items' => count($template->template_data['line_items'] ?? []),
            'Payment Terms' => ($template->template_data['payment_terms'] ?? 30).' days',
            'Created At' => $template->created_at->format('Y-m-d H:i:s'),
        ]);

        // Log the action
        $this->logExecution('template_created', [
            'template_id' => $template->id,
            'template_name' => $template->name,
            'customer_id' => $customer?->id,
        ]);

        return self::SUCCESS;
    }

    /**
     * Prepare template data from input.
     */
    protected function prepareTemplateData(array $input): array
    {
        $lineItems = [];

        // Handle line items input
        if (isset($input['items'])) {
            if (is_string($input['items'])) {
                // Try to parse as JSON first
                $decoded = json_decode($input['items'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $lineItems = $decoded;
                } else {
                    // Parse as comma-separated descriptions
                    $descriptions = explode(',', $input['items']);
                    foreach ($descriptions as $desc) {
                        $lineItems[] = [
                            'id' => 'item-'.uniqid(),
                            'description' => trim($desc),
                            'quantity' => 1,
                            'unit_price' => 100.00,
                            'tax_rate' => 0,
                        ];
                    }
                }
            } elseif (is_array($input['items'])) {
                $lineItems = $input['items'];
            }
        }

        // Interactive mode for line items
        if (isset($input['interactive']) && empty($lineItems)) {
            $lineItems = $this->collectLineItemsInteractively();
        }

        // Default line item if none provided
        if (empty($lineItems)) {
            $lineItems = [
                [
                    'id' => 'item-'.uniqid(),
                    'description' => 'Service/Product',
                    'quantity' => 1,
                    'unit_price' => 100.00,
                    'tax_rate' => 0,
                ],
            ];
        }

        return [
            'template_data' => [
                'notes' => $input['notes'] ?? null,
                'terms' => $input['terms'] ?? null,
                'payment_terms' => (int) ($input['payment_terms'] ?? 30),
                'line_items' => $lineItems,
            ],
            'settings' => [
                'auto_number' => true,
                'number_prefix' => 'TPL-',
                'send_email' => false,
                'generate_pdf' => false,
            ],
        ];
    }

    /**
     * Collect line items interactively.
     */
    protected function collectLineItemsInteractively(): array
    {
        $lineItems = [];
        $this->info('Adding line items (press Enter with empty description to finish):');

        while (true) {
            $description = $this->ask('Item description');
            if (empty($description)) {
                break;
            }

            $quantity = $this->ask('Quantity', 1);
            $unitPrice = $this->ask('Unit price', 100.00);
            $taxRate = $this->ask('Tax rate (%)', 0);

            $lineItems[] = [
                'id' => 'item-'.uniqid(),
                'description' => $description,
                'quantity' => (float) $quantity,
                'unit_price' => (float) $unitPrice,
                'tax_rate' => (float) $taxRate,
            ];

            $this->line("✓ Added: {$description} x {$quantity} @ \${$unitPrice}");
        }

        return $lineItems;
    }

    /**
     * Parse natural language input for template creation.
     */
    protected function parseNaturalLanguageTemplateInput(string $input): array
    {
        $parsed = [
            'name' => null,
            'customer' => null,
            'description' => null,
            'items' => [],
            'currency' => null,
        ];

        // Extract template name
        if (preg_match('/template["\']?\s+["\']?([^"\']+)["\']?/i', $input, $matches)) {
            $parsed['name'] = $matches[1];
        }

        // Extract customer
        if (preg_match('/for\s+(customer\s+)?([A-Za-z0-9\-]+)/i', $input, $matches)) {
            $parsed['customer'] = $matches[2];
        }

        // Extract items/description
        if (preg_match('/with\s+(.+?)(?:\s+for|\s+using|\s+$)/i', $input, $matches)) {
            $itemsText = $matches[1];
            $itemDescriptions = preg_split('/\s+(?:and|,)\s+/', $itemsText);
            $parsed['items'] = array_map(fn ($desc) => trim($desc), $itemDescriptions);
        }

        // Extract currency
        if (preg_match('/(USD|EUR|GBP|CAD|AUD)/i', $input, $matches)) {
            $parsed['currency'] = strtoupper($matches[1]);
        }

        // Generate default name if not found
        if (empty($parsed['name'])) {
            $parsed['name'] = 'Template '.date('Y-m-d');
        }

        return $parsed;
    }

    /**
     * Get usage examples for this command.
     */
    protected function getExamples(): array
    {
        return [
            'invoice:template:create "Web Design Services" --customer=CUST-001',
            'invoice:template:create "Consulting Template" --from-invoice=INV-2024-001',
            'invoice:template:create "General Services" --items="Web Development,SEO Services,Hosting" --currency=USD',
            'invoice:template:create --natural="Create template for monthly recurring services for customer ABC-123 with web hosting and support"',
            'invoice:template:create "Custom Template" --interactive',
        ];
    }
}
