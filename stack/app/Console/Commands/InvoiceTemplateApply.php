<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Invoice;
use App\Services\InvoiceCliService;
use App\Services\InvoiceTemplateService;

class InvoiceTemplateApply extends InvoiceBaseCommand
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'invoice:template:apply
                           {template : Template ID, name, or UUID}
                           {--customer= : Customer ID (overrides template customer)}
                           {--currency= : Override currency}
                           {--date= : Issue date (Y-m-d, defaults to today)}
                           {--due-date= : Due date (Y-m-d, calculated from template)}
                           {--notes= : Override notes}
                           {--terms= : Override terms}
                           {--items-overrides= : JSON string of item overrides}
                           {--additional-items= : JSON string of additional line items}
                           {--dry-run : Preview invoice data without creating}
                           {--create-invoice : Create the invoice after applying template}
                           {--format=table : Output format (table, json, text)}
                           {--company= : Company ID (overrides current company)}
                           {--quiet : Suppress output}';

    /**
     * The console command description.
     */
    protected $description = 'Apply an invoice template to create invoice data or actual invoice';

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

            // Get customer
            $customer = $this->getCustomer($input, $template);

            // Prepare overrides
            $overrides = $this->prepareOverrides($input);

            // Apply template
            $templateService = new InvoiceTemplateService(
                app(\App\Services\ContextService::class),
                app(\App\Services\AuthService::class)
            );

            $invoiceData = $templateService->applyTemplate($template, $customer, $overrides, auth()->user());

            // Display results
            $this->displayAppliedTemplate($template, $customer, $invoiceData, $input);

            // Create invoice if requested
            if (isset($input['create_invoice']) && ! isset($input['dry_run'])) {
                return $this->createInvoiceFromTemplate($invoiceData, $input);
            }

            // Log the action
            $this->logExecution('template_applied', [
                'template_id' => $template->id,
                'template_name' => $template->name,
                'customer_id' => $customer?->id,
                'dry_run' => isset($input['dry_run']),
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

        if (! $template->is_active) {
            $this->error("Template '{$template->name}' is not active.");
            exit(1);
        }

        return $template;
    }

    /**
     * Get customer for template application.
     */
    protected function getCustomer(array $input, $template): ?Customer
    {
        $customerId = $input['customer'] ?? $template->customer_id;

        if (! $customerId) {
            $this->warn('No customer specified. This will create a general template application.');

            return null;
        }

        $customer = Customer::where('company_id', $this->company->id)
            ->where('id', $customerId)
            ->first();

        if (! $customer) {
            $this->error("Customer '{$customerId}' not found.");
            exit(1);
        }

        return $customer;
    }

    /**
     * Prepare overrides for template application.
     */
    protected function prepareOverrides(array $input): array
    {
        $overrides = [];

        // Basic field overrides
        if (isset($input['currency'])) {
            $overrides['currency'] = $input['currency'];
        }

        if (isset($input['date'])) {
            $overrides['issue_date'] = $input['date'];
        }

        if (isset($input['due_date'])) {
            $overrides['due_date'] = $input['due_date'];
        } elseif (isset($input['date'])) {
            // Calculate due date from issue date and template payment terms
            $templateData = $this->getTemplateFromIdentifier($input['template'])->template_data ?? [];
            $paymentTerms = $templateData['payment_terms'] ?? 30;
            $overrides['due_date'] = \Carbon\Carbon::parse($input['date'])->addDays($paymentTerms)->format('Y-m-d');
        }

        if (isset($input['notes'])) {
            $overrides['notes'] = $input['notes'];
        }

        if (isset($input['terms'])) {
            $overrides['terms'] = $input['terms'];
        }

        // Line item overrides
        if (isset($input['items_overrides'])) {
            $decoded = json_decode($input['items_overrides'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $overrides['line_items_overrides'] = $decoded;
            } else {
                $this->warn('Invalid JSON in items_overrides. Ignoring.');
            }
        }

        // Additional line items
        if (isset($input['additional_items'])) {
            $decoded = json_decode($input['additional_items'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $overrides['additional_line_items'] = $decoded;
            } else {
                $this->warn('Invalid JSON in additional_items. Ignoring.');
            }
        }

        return $overrides;
    }

    /**
     * Display applied template results.
     */
    protected function displayAppliedTemplate($template, $customer, array $invoiceData, array $input): void
    {
        $format = $input['format'] ?? 'table';

        if (isset($input['dry_run'])) {
            $this->info('TEMPLATE APPLICATION PREVIEW (Dry Run)');
        } else {
            $this->info('TEMPLATE APPLIED SUCCESSFULLY');
        }

        $this->line(str_repeat('=', 40));
        $this->line("Template: {$template->name}");
        $this->line('Customer: '.($customer?->name ?? 'General'));
        $this->line("Currency: {$invoiceData['currency']}");
        $this->line("Issue Date: {$invoiceData['issue_date']}");
        $this->line("Due Date: {$invoiceData['due_date']}");
        $this->line('Notes: '.($invoiceData['notes'] ?? 'N/A'));
        $this->line('Terms: '.($invoiceData['terms'] ?? 'N/A'));
        $this->line('');

        // Display line items
        $this->displayInvoiceLineItems($invoiceData['line_items'] ?? []);

        // Display totals
        $totals = $this->calculateInvoiceTotals($invoiceData['line_items'] ?? []);
        $this->line(str_repeat('-', 40));
        $this->line('Subtotal: $'.number_format($totals['subtotal'], 2));
        $this->line('Tax: $'.number_format($totals['tax'], 2));
        $this->line('Total: $'.number_format($totals['total'], 2));

        if (isset($input['dry_run'])) {
            $this->line('');
            $this->info('This is a preview. Use --create-invoice to create the actual invoice.');
        } elseif (! isset($input['create_invoice'])) {
            $this->line('');
            $this->info('Template data prepared. Use --create-invoice to create the actual invoice.');
        }
    }

    /**
     * Display invoice line items.
     */
    protected function displayInvoiceLineItems(array $lineItems): void
    {
        if (empty($lineItems)) {
            return;
        }

        $this->info('Line Items:');
        $this->line(str_repeat('-', 30));

        $tableData = [];
        foreach ($lineItems as $index => $item) {
            $quantity = $item['quantity'] ?? 0;
            $unitPrice = $item['unit_price'] ?? 0;
            $taxRate = $item['tax_rate'] ?? 0;
            $discount = $item['discount_amount'] ?? 0;

            $itemTotal = $quantity * $unitPrice;
            $itemTax = $itemTotal * ($taxRate / 100);
            $itemFinal = $itemTotal + $itemTax - $discount;

            $tableData[] = [
                '#' => $index + 1,
                'Description' => substr($item['description'] ?? '', 0, 25),
                'Qty' => $quantity,
                'Price' => '$'.number_format($unitPrice, 2),
                'Tax' => $taxRate.'%',
                'Total' => '$'.number_format($itemFinal, 2),
            ];
        }

        $this->table(['#', 'Description', 'Qty', 'Price', 'Tax', 'Total'], $tableData);
    }

    /**
     * Calculate invoice totals.
     */
    protected function calculateInvoiceTotals(array $lineItems): array
    {
        $subtotal = 0;
        $tax = 0;

        foreach ($lineItems as $item) {
            $quantity = $item['quantity'] ?? 0;
            $unitPrice = $item['unit_price'] ?? 0;
            $taxRate = $item['tax_rate'] ?? 0;
            $discount = $item['discount_amount'] ?? 0;

            $itemTotal = $quantity * $unitPrice;
            $itemTax = $itemTotal * ($taxRate / 100);

            $subtotal += $itemTotal - $discount;
            $tax += $itemTax;
        }

        return [
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $subtotal + $tax,
        ];
    }

    /**
     * Create invoice from template data.
     */
    protected function createInvoiceFromTemplate(array $invoiceData, array $input): int
    {
        $this->line('');
        $this->info('Creating invoice from template...');

        $invoiceService = new InvoiceCliService;

        // Create the invoice
        $invoice = $invoiceService->createInvoice(
            array_intersect_key($invoiceData, [
                'company_id' => true,
                'customer_id' => true,
                'issue_date' => true,
                'due_date' => true,
                'currency' => true,
                'notes' => true,
                'terms' => true,
            ]),
            $invoiceData['line_items'] ?? []
        );

        $this->displaySuccess('Invoice created successfully', [
            'Invoice Number' => $invoice->invoice_number,
            'Customer' => $invoice->customer?->name,
            'Total Amount' => '$'.number_format($invoice->total_amount, 2),
            'Status' => $invoice->status,
            'Created At' => $invoice->created_at->format('Y-m-d H:i:s'),
        ]);

        // Log the action
        $this->logExecution('invoice_created_from_template', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'template_id' => $input['template'],
        ]);

        return self::SUCCESS;
    }

    /**
     * Get template by identifier (helper method).
     */
    protected function getTemplateFromIdentifier(string $identifier)
    {
        // This is a simplified version - in production, you might want to cache this
        return \App\Models\InvoiceTemplate::where('company_id', $this->company->id)
            ->where(function ($query) use ($identifier) {
                $query->where('id', $identifier)
                    ->orWhere('name', $identifier)
                    ->orWhere('name', 'ilike', "%{$identifier}%");
            })
            ->first();
    }

    /**
     * Get usage examples for this command.
     */
    protected function getExamples(): array
    {
        return [
            'invoice:template:apply "Web Design Template" --customer=CUST-001 --dry-run',
            'invoice:template:apply TPL-001 --customer=CUST-002 --create-invoice',
            'invoice:template:apply "Services" --date=2024-02-01 --notes="Monthly services" --dry-run',
            'invoice:template:apply 12345 --customer=CUST-003 --currency=EUR --items-overrides=\'{"item-123": {"unit_price": 150}}\'',
        ];
    }
}
