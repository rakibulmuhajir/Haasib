<?php

namespace App\Console\Commands;

use App\Services\InvoiceTemplateService;

class InvoiceTemplateList extends InvoiceBaseCommand
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'invoice:template:list
                           {--customer= : Filter by customer ID (use "general" for general templates)}
                           {--currency= : Filter by currency}
                           {--active : Show only active templates}
                           {--inactive : Show only inactive templates}
                           {--search= : Search in name and description}
                           {--limit=50 : Limit number of results}
                           {--sort=name : Sort column (name, created_at, updated_at)}
                           {--order=asc : Sort order (asc, desc)}
                           {--format=table : Output format (table, json, csv, text)}
                           {--export= : Export to file}
                           {--summary : Show summary statistics only}
                           {--company= : Company ID (overrides current company)}
                           {--quiet : Suppress output}';

    /**
     * The console command description.
     */
    protected $description = 'List invoice templates for a company';

    /**
     * Execute the command logic.
     */
    protected function executeCommand(): int
    {
        $input = $this->parseInput();

        try {
            $templateService = new InvoiceTemplateService(
                app(\App\Services\ContextService::class),
                app(\App\Services\AuthService::class)
            );

            // Prepare filters
            $filters = $this->prepareFilters($input);

            // Get templates
            $templates = $templateService->getTemplatesForCompany(
                $this->company,
                auth()->user(),
                $filters
            );

            // Apply sorting
            $templates = $this->applySorting($templates, $input);

            // Apply limit
            if (isset($input['limit'])) {
                $templates = $templates->take($input['limit']);
            }

            // Show summary only if requested, otherwise show templates
            if (isset($input['summary'])) {
                $this->displaySummary($templates, $filters);

                return self::SUCCESS;
            }

            // Format and display results
            $this->displayTemplates($templates, $input);

            // Export if requested
            if (isset($input['export'])) {
                $this->exportTemplates($templates, $input['export'], $input);
            }

            // Show summary
            $this->displaySummary($templates, $filters);

            // Log the action
            $this->logExecution('template_list_viewed', [
                'company_id' => $this->company->id,
                'filters' => $filters,
                'count' => $templates->count(),
            ]);

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->handleServiceException($exception);

            return self::FAILURE;
        }
    }

    /**
     * Prepare filters from input.
     */
    protected function prepareFilters(array $input): array
    {
        $filters = [];

        if (isset($input['active'])) {
            $filters['is_active'] = true;
        } elseif (isset($input['inactive'])) {
            $filters['is_active'] = false;
        }

        if (isset($input['customer'])) {
            $filters['customer_id'] = $input['customer'];
        }

        if (isset($input['currency'])) {
            $filters['currency'] = strtoupper($input['currency']);
        }

        if (isset($input['search'])) {
            $filters['search'] = $input['search'];
        }

        return $filters;
    }

    /**
     * Apply sorting to templates collection.
     */
    protected function applySorting($templates, array $input)
    {
        $sort = $input['sort'] ?? 'name';
        $order = $input['order'] ?? 'asc';

        return $templates->sortBy(function ($template) use ($sort) {
            return match ($sort) {
                'created_at' => $template->created_at,
                'updated_at' => $template->updated_at,
                'currency' => $template->currency,
                'customer_name' => $template->customer?->name ?? 'ZZZ', // General templates last
                default => $template->name,
            };
        }, SORT_REGULAR, $order === 'desc')->values();
    }

    /**
     * Display templates in the requested format.
     */
    protected function displayTemplates($templates, array $input): void
    {
        if ($templates->isEmpty()) {
            $this->info('No templates found matching the criteria.');

            return;
        }

        $format = $input['format'] ?? 'table';

        match ($format) {
            'json' => $this->outputJson($templates),
            'csv' => $this->outputCsv($templates),
            'text' => $this->outputText($templates),
            default => $this->outputTable($templates),
        };
    }

    /**
     * Output templates as table.
     */
    protected function outputTable($templates): void
    {
        $tableData = $templates->map(function ($template) {
            $templateData = $template->template_data ?? [];
            $lineItemsCount = count($templateData['line_items'] ?? []);
            $totalAmount = $this->calculateTemplateTotal($template);

            return [
                'ID' => substr($template->id, 0, 8),
                'Name' => $template->name,
                'Customer' => $template->customer?->name ?? 'General',
                'Currency' => $template->currency,
                'Items' => $lineItemsCount,
                'Total' => '$'.number_format($totalAmount, 2),
                'Status' => $template->is_active ? 'Active' : 'Inactive',
                'Updated' => $template->updated_at->format('M j, Y'),
            ];
        })->toArray();

        $this->table(
            ['ID', 'Name', 'Customer', 'Currency', 'Items', 'Total', 'Status', 'Updated'],
            $tableData
        );
    }

    /**
     * Output templates as JSON.
     */
    protected function outputJson($templates): void
    {
        $data = $templates->map(function ($template) {
            return [
                'id' => $template->id,
                'name' => $template->name,
                'description' => $template->description,
                'customer' => $template->customer?->only(['id', 'name']),
                'currency' => $template->currency,
                'is_active' => $template->is_active,
                'line_items_count' => count($template->template_data['line_items'] ?? []),
                'total_amount' => $this->calculateTemplateTotal($template),
                'created_at' => $template->created_at,
                'updated_at' => $template->updated_at,
            ];
        })->toArray();

        $this->line(json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * Output templates as CSV.
     */
    protected function outputCsv($templates): void
    {
        $headers = ['ID', 'Name', 'Description', 'Customer', 'Currency', 'Items', 'Total', 'Status', 'Created', 'Updated'];

        $rows = $templates->map(function ($template) {
            $templateData = $template->template_data ?? [];

            return [
                $template->id,
                $template->name,
                $template->description ?? '',
                $template->customer?->name ?? 'General',
                $template->currency,
                count($templateData['line_items'] ?? []),
                $this->calculateTemplateTotal($template),
                $template->is_active ? 'Active' : 'Inactive',
                $template->created_at->format('Y-m-d H:i:s'),
                $template->updated_at->format('Y-m-d H:i:s'),
            ];
        })->toArray();

        $this->line(implode(',', $headers));
        foreach ($rows as $row) {
            $escapedRow = array_map(fn ($cell) => '"'.str_replace('"', '""', $cell).'"', $row);
            $this->line(implode(',', $escapedRow));
        }
    }

    /**
     * Output templates as plain text.
     */
    protected function outputText($templates): void
    {
        foreach ($templates as $template) {
            $templateData = $template->template_data ?? [];
            $lineItemsCount = count($templateData['line_items'] ?? []);
            $totalAmount = $this->calculateTemplateTotal($template);

            $this->line(str_repeat('=', 60));
            $this->line("Template: {$template->name}");
            $this->line("ID: {$template->id}");
            $this->line('Customer: '.($template->customer?->name ?? 'General'));
            $this->line("Currency: {$template->currency}");
            $this->line("Items: {$lineItemsCount}");
            $this->line('Total: ${'.number_format($totalAmount, 2).'}');
            $this->line('Status: '.($template->is_active ? 'Active' : 'Inactive'));

            if ($template->description) {
                $this->line("Description: {$template->description}");
            }

            $this->line('Updated: '.$template->updated_at->format('Y-m-d H:i:s'));
            $this->line('');
        }
    }

    /**
     * Export templates to file.
     */
    protected function exportTemplates($templates, string $filename, array $input): void
    {
        $format = $input['format'] ?? 'table';

        // Capture output
        ob_start();
        match ($format) {
            'json' => $this->outputJson($templates),
            'csv' => $this->outputCsv($templates),
            'text' => $this->outputText($templates),
            default => $this->outputTable($templates),
        };
        $content = ob_get_clean();

        // Write to file
        file_put_contents($filename, $content);
        $this->info("Templates exported to: {$filename}");
    }

    /**
     * Display summary information.
     */
    protected function displaySummary($templates, array $filters): void
    {
        $summary = [
            'Total Templates' => $templates->count(),
            'Active Templates' => $templates->where('is_active', true)->count(),
            'Inactive Templates' => $templates->where('is_active', false)->count(),
            'General Templates' => $templates->whereNull('customer_id')->count(),
            'Customer-Specific' => $templates->whereNotNull('customer_id')->count(),
        ];

        $this->line('');
        $this->info('Summary:');
        foreach ($summary as $key => $value) {
            $this->line("  {$key}: {$value}");
        }

        // Show active filters
        $activeFilters = array_filter($filters, fn ($v) => $v !== null);
        if (! empty($activeFilters)) {
            $this->line('');
            $this->info('Active Filters:');
            foreach ($activeFilters as $key => $value) {
                $this->line("  {$key}: {$value}");
            }
        }
    }

    /**
     * Calculate total amount for a template.
     */
    protected function calculateTemplateTotal($template): float
    {
        $templateData = $template->template_data ?? [];
        $lineItems = $templateData['line_items'] ?? [];

        $total = 0;
        foreach ($lineItems as $item) {
            $itemTotal = ($item['quantity'] ?? 0) * ($item['unit_price'] ?? 0);
            $itemTax = $itemTotal * (($item['tax_rate'] ?? 0) / 100);
            $itemDiscount = $item['discount_amount'] ?? 0;
            $total += $itemTotal + $itemTax - $itemDiscount;
        }

        return $total;
    }

    /**
     * Get usage examples for this command.
     */
    protected function getExamples(): array
    {
        return [
            'invoice:template:list',
            'invoice:template:list --customer=CUST-001',
            'invoice:template:list --active --currency=USD',
            'invoice:template:list --search="Web Design" --limit=10',
            'invoice:template:list --customer=general --format=json',
            'invoice:template:list --sort=updated_at --order=desc --export=templates.csv',
        ];
    }
}
