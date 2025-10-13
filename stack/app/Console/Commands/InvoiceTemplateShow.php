<?php

namespace App\Console\Commands;

class InvoiceTemplateShow extends InvoiceBaseCommand
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'invoice:template:show
                           {template : Template ID, name, or UUID}
                           {--format=table : Output format (table, json, csv, text)}
                           {--with-items : Show detailed line items}
                           {--with-settings : Show template settings}
                           {--summary : Show summary only}
                           {--company= : Company ID (overrides current company)}
                           {--quiet : Suppress output}';

    /**
     * The console command description.
     */
    protected $description = 'Display detailed information about an invoice template';

    /**
     * Execute the command logic.
     */
    protected function executeCommand(): int
    {
        $input = $this->parseInput();
        $identifier = $input['template'] ?? $this->argument('template');

        try {
            $template = $this->findTemplate($identifier);

            // Determine display mode
            $summaryOnly = isset($input['summary']);
            $withItems = isset($input['with_items']) || ! $summaryOnly;
            $withSettings = isset($input['with_settings']);

            // Format and display results
            $this->displayTemplate($template, $input, $withItems, $withSettings, $summaryOnly);

            // Log the action
            $this->logExecution('template_viewed', [
                'template_id' => $template->id,
                'template_name' => $template->name,
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

        // Try numeric ID (if it's a short ID)
        if (! isset($template) && is_numeric($identifier) && strlen($identifier) < 10) {
            $template = \App\Models\InvoiceTemplate::where('id', $identifier)
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
     * Display template information.
     */
    protected function displayTemplate($template, array $input, bool $withItems, bool $withSettings, bool $summaryOnly): void
    {
        $format = $input['format'] ?? 'table';

        if ($summaryOnly) {
            $this->displaySummary($template);

            return;
        }

        match ($format) {
            'json' => $this->outputJson($template, $withItems, $withSettings),
            'csv' => $this->outputCsv($template, $withItems, $withSettings),
            'text' => $this->outputText($template, $withItems, $withSettings),
            default => $this->outputTable($template, $withItems, $withSettings),
        };
    }

    /**
     * Display template summary.
     */
    protected function displaySummary($template): void
    {
        $summary = $template->getSummary();

        $this->info($summary['name']);
        $this->line(str_repeat('=', strlen($summary['name'])));
        $this->line("ID: {$template->id}");
        $this->line('Description: '.($summary['description'] ?? 'N/A'));
        $this->line('Customer: '.($summary['customer_name'] ?? 'General'));
        $this->line("Currency: {$summary['currency']}");
        $this->line("Line Items: {$summary['line_items_count']}");
        $this->line('Subtotal: ${'.number_format($summary['subtotal'], 2).'}');
        $this->line('Tax: ${'.number_format($summary['tax_amount'], 2).'}');
        $this->line('Total: ${'.number_format($summary['total_amount'], 2).'}');
        $this->line('Status: '.($summary['is_active'] ? 'Active' : 'Inactive'));
        $this->line('Created: '.$template->created_at->format('Y-m-d H:i:s'));
        $this->line('Updated: '.$template->updated_at->format('Y-m-d H:i:s'));
    }

    /**
     * Output template as table.
     */
    protected function outputTable($template, bool $withItems, bool $withSettings): void
    {
        // Basic information
        $this->info($template->name);
        $this->line(str_repeat('=', strlen($template->name)));

        $basicInfo = [
            'ID' => $template->id,
            'Description' => $template->description ?? 'N/A',
            'Customer' => $template->customer?->name ?? 'General',
            'Currency' => $template->currency,
            'Status' => $template->is_active ? 'Active' : 'Inactive',
            'Created By' => $template->creator?->name ?? 'N/A',
            'Created At' => $template->created_at->format('Y-m-d H:i:s'),
            'Updated At' => $template->updated_at->format('Y-m-d H:i:s'),
        ];

        foreach ($basicInfo as $key => $value) {
            $this->line("{$key}: {$value}");
        }

        $this->line('');

        // Template data
        $templateData = $template->template_data ?? [];
        $this->info('Template Details:');
        $this->line('Notes: '.($templateData['notes'] ?? 'N/A'));
        $this->line('Terms: '.($templateData['terms'] ?? 'N/A'));
        $this->line('Payment Terms: '.($templateData['payment_terms'] ?? 30).' days');

        // Line items
        if ($withItems) {
            $this->displayLineItems($templateData['line_items'] ?? []);
        }

        // Settings
        if ($withSettings) {
            $this->displaySettings($template->settings ?? []);
        }
    }

    /**
     * Display line items.
     */
    protected function displayLineItems(array $lineItems): void
    {
        if (empty($lineItems)) {
            return;
        }

        $this->line('');
        $this->info('Line Items:');
        $this->line(str_repeat('-', 40));

        $tableData = [];
        $subtotal = 0;
        $totalTax = 0;

        foreach ($lineItems as $index => $item) {
            $quantity = $item['quantity'] ?? 0;
            $unitPrice = $item['unit_price'] ?? 0;
            $taxRate = $item['tax_rate'] ?? 0;
            $discount = $item['discount_amount'] ?? 0;

            $itemTotal = $quantity * $unitPrice;
            $itemTax = $itemTotal * ($taxRate / 100);
            $itemFinal = $itemTotal + $itemTax - $discount;

            $subtotal += $itemTotal;
            $totalTax += $itemTax;

            $tableData[] = [
                '#' => $index + 1,
                'Description' => substr($item['description'] ?? '', 0, 30),
                'Qty' => $quantity,
                'Price' => '$'.number_format($unitPrice, 2),
                'Tax' => $taxRate.'%',
                'Discount' => '$'.number_format($discount, 2),
                'Total' => '$'.number_format($itemFinal, 2),
            ];
        }

        $this->table(['#', 'Description', 'Qty', 'Price', 'Tax', 'Discount', 'Total'], $tableData);

        $this->line(str_repeat('-', 40));
        $this->line('Subtotal: $'.number_format($subtotal, 2));
        $this->line('Tax: $'.number_format($totalTax, 2));
        $this->line('Total: $'.number_format($subtotal + $totalTax, 2));
    }

    /**
     * Display template settings.
     */
    protected function displaySettings(array $settings): void
    {
        if (empty($settings)) {
            return;
        }

        $this->line('');
        $this->info('Template Settings:');
        $this->line(str_repeat('-', 30));

        foreach ($settings as $key => $value) {
            if (is_array($value)) {
                $this->line("{$key}:");
                foreach ($value as $subKey => $subValue) {
                    $this->line("  {$subKey}: ".($this->formatValue($subValue)));
                }
            } else {
                $this->line("{$key}: ".$this->formatValue($value));
            }
        }
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

        return (string) $value;
    }

    /**
     * Output template as JSON.
     */
    protected function outputJson($template, bool $withItems, bool $withSettings): void
    {
        $data = [
            'id' => $template->id,
            'name' => $template->name,
            'description' => $template->description,
            'customer' => $template->customer?->only(['id', 'name']),
            'currency' => $template->currency,
            'is_active' => $template->is_active,
            'creator' => $template->creator?->only(['id', 'name']),
            'created_at' => $template->created_at,
            'updated_at' => $template->updated_at,
        ];

        if ($withItems) {
            $data['template_data'] = $template->template_data;
        }

        if ($withSettings) {
            $data['settings'] = $template->settings;
        }

        $this->line(json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * Output template as CSV.
     */
    protected function outputCsv($template, bool $withItems, bool $withSettings): void
    {
        $headers = ['Field', 'Value'];
        $this->line(implode(',', $headers));

        $fields = [
            'ID' => $template->id,
            'Name' => '"'.str_replace('"', '""', $template->name).'"',
            'Description' => '"'.str_replace('"', '""', $template->description ?? '').'"',
            'Customer' => '"'.str_replace('"', '""', $template->customer?->name ?? 'General').'"',
            'Currency' => $template->currency,
            'Status' => $template->is_active ? 'Active' : 'Inactive',
            'Created At' => $template->created_at->format('Y-m-d H:i:s'),
        ];

        foreach ($fields as $key => $value) {
            $this->line("{$key},{$value}");
        }

        if ($withItems) {
            $this->line('');
            $this->line('Line Items');
            $this->line('Description,Quantity,Unit Price,Tax Rate,Discount Amount');

            $templateData = $template->template_data ?? [];
            foreach ($templateData['line_items'] ?? [] as $item) {
                $description = '"'.str_replace('"', '""', $item['description'] ?? '').'"';
                $this->line($description.','.$item['quantity'].','.$item['unit_price'].','.$item['tax_rate'].','.($item['discount_amount'] ?? 0));
            }
        }
    }

    /**
     * Output template as plain text.
     */
    protected function outputText($template, bool $withItems, bool $withSettings): void
    {
        $this->line('TEMPLATE DETAILS');
        $this->line(str_repeat('=', 50));
        $this->line("Name: {$template->name}");
        $this->line("ID: {$template->id}");
        $this->line('Description: '.($template->description ?? 'N/A'));
        $this->line('Customer: '.($template->customer?->name ?? 'General'));
        $this->line("Currency: {$template->currency}");
        $this->line('Status: '.($template->is_active ? 'Active' : 'Inactive'));
        $this->line('Created By: '.($template->creator?->name ?? 'N/A'));
        $this->line('Created At: '.$template->created_at->format('Y-m-d H:i:s'));
        $this->line('Updated At: '.$template->updated_at->format('Y-m-d H:i:s'));

        if ($withItems) {
            $this->line('');
            $this->line('LINE ITEMS');
            $this->line(str_repeat('-', 30));

            $templateData = $template->template_data ?? [];
            foreach ($templateData['line_items'] ?? [] as $index => $item) {
                $this->line('Item '.($index + 1).':');
                $this->line('  Description: '.($item['description'] ?? 'N/A'));
                $this->line('  Quantity: '.($item['quantity'] ?? 0));
                $this->line('  Unit Price: $'.number_format($item['unit_price'] ?? 0, 2));
                $this->line('  Tax Rate: '.($item['tax_rate'] ?? 0).'%');
                $this->line('  Discount: $'.number_format($item['discount_amount'] ?? 0, 2));
                $this->line('');
            }
        }

        if ($withSettings) {
            $this->line('SETTINGS');
            $this->line(str_repeat('-', 30));

            foreach ($template->settings ?? [] as $key => $value) {
                $this->line("{$key}: ".$this->formatValue($value));
            }
        }
    }

    /**
     * Get usage examples for this command.
     */
    protected function getExamples(): array
    {
        return [
            'invoice:template:show TPL-001',
            'invoice:template:show "Web Design Template"',
            'invoice:template:show 12345 --with-items --with-settings',
            'invoice:template:show uuid-string --format=json',
            'invoice:template:show "Services" --summary',
        ];
    }
}
