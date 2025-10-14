<?php

namespace App\Console\Commands;

use App\Models\Invoice;

class CreditNoteCreate extends CreditNoteBaseCommand
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'creditnote:create
                           {invoice : Invoice ID or number}
                           {amount : Credit amount}
                           {--reason= : Reason for the credit note}
                           {--currency= : Currency code (defaults to invoice currency)}
                           {--tax= : Tax rate or amount}
                           {--notes= : Additional notes}
                           {--items= : Items in format "Description:Quantity:Price:TaxRate"}
                           {--input= : Natural language input}
                           {--interactive : Interactive mode}
                           {--format=table : Output format (table, json)}
                           {--company= : Company ID (overrides current company)}
                           {--dry-run : Preview without creating}';

    /**
     * The console command description.
     */
    protected $description = 'Create a new credit note against an invoice';

    /**
     * Handle the command logic.
     */
    protected function handleCommand(): int
    {
        $input = $this->parseInput();

        // Parse input from different sources
        $data = $this->parseCreditNoteInput($input);

        // Find and validate invoice
        $invoice = $this->findInvoice($data['invoice_id']);

        // Validate credit amount against invoice balance
        $this->validateCreditAmount($data['amount'], $invoice);

        // Show preview in dry-run mode
        if (isset($input['dry-run'])) {
            $this->displayPreview($data, $invoice);

            return self::SUCCESS;
        }

        // Create the credit note
        $creditNote = $this->creditNoteService->createCreditNote(
            $this->company,
            $data,
            auth()->user()
        );

        // Display results
        $this->displaySuccessMessage($creditNote);

        // Log the action
        $this->logExecution('credit_note_created', [
            'credit_note_id' => $creditNote->id,
            'invoice_id' => $invoice->id,
            'amount' => $creditNote->total_amount,
        ]);

        return self::SUCCESS;
    }

    /**
     * Parse credit note input from various sources.
     */
    protected function parseCreditNoteInput(array $input): array
    {
        // Natural language input
        if (isset($input['input'])) {
            return $this->parseNaturalLanguageInput($input['input']);
        }

        // Interactive mode
        if (isset($input['interactive'])) {
            return $this->collectInteractiveInput();
        }

        // Direct input from arguments/options
        return [
            'invoice_id' => $input['invoice'],
            'amount' => $input['amount'],
            'reason' => $input['reason'] ?? 'Credit adjustment',
            'currency' => $input['currency'] ?? null,
            'tax_amount' => $this->calculateTaxAmount($input),
            'total_amount' => $input['amount'],
            'notes' => $input['notes'] ?? null,
            'items' => $this->parseItems($input['items'] ?? null),
        ];
    }

    /**
     * Collect input interactively.
     */
    protected function collectInteractiveInput(): array
    {
        $this->info('Create Credit Note - Interactive Mode');
        $this->line(str_repeat('-', 40));

        // Find invoice
        $invoiceId = $this->ask('Invoice ID or number:');
        $invoice = $this->findInvoice($invoiceId);

        $this->line("Invoice: {$invoice->invoice_number} (Balance due: \${$invoice->balance_due})");
        $this->line('Customer: '.$invoice->customer->name);
        $this->line('');

        // Get amount
        $amount = $this->ask('Credit amount:');
        while (! is_numeric($amount) || $amount <= 0) {
            $this->error('Please enter a valid positive number.');
            $amount = $this->ask('Credit amount:');
        }

        // Get reason
        $reason = $this->ask('Reason for credit note:');
        while (empty($reason)) {
            $this->error('Reason is required.');
            $reason = $this->ask('Reason for credit note:');
        }

        // Get tax information
        $includeTax = $this->confirm('Include tax?');
        $taxAmount = 0;
        if ($includeTax) {
            $taxOption = $this->choice('Tax calculation:', [
                'rate' => 'Enter tax rate (e.g., 10 for 10%)',
                'amount' => 'Enter tax amount directly',
                'auto' => 'Auto-calculate based on amount',
            ]);

            switch ($taxOption) {
                case 'rate':
                    $taxRate = $this->ask('Tax rate (%):');
                    $taxAmount = $amount * ($taxRate / 100);
                    break;
                case 'amount':
                    $taxAmount = $this->ask('Tax amount:');
                    break;
                case 'auto':
                    $taxAmount = $amount * 0.1; // Default 10%
                    break;
            }
        }

        // Get items
        $includeItems = $this->confirm('Add line items?');
        $items = [];
        if ($includeItems) {
            $items = $this->collectItems();
        }

        // Get optional notes
        $includeNotes = $this->confirm('Add notes?');
        $notes = $includeNotes ? $this->ask('Notes:') : null;

        return [
            'invoice_id' => $invoice->id,
            'amount' => $amount,
            'tax_amount' => $taxAmount,
            'total_amount' => $amount + $taxAmount,
            'reason' => $reason,
            'currency' => $invoice->currency,
            'notes' => $notes,
            'items' => $items,
        ];
    }

    /**
     * Collect line items interactively.
     */
    protected function collectItems(): array
    {
        $items = [];
        $itemCount = 1;

        do {
            $this->line("\nItem #{$itemCount}:");
            $description = $this->ask('Description:');
            $quantity = $this->ask('Quantity:');
            $unitPrice = $this->ask('Unit price:');
            $taxRate = $this->ask('Tax rate (%) (optional):', 0);
            $discountAmount = $this->ask('Discount amount (optional):', 0);

            $items[] = [
                'description' => $description,
                'quantity' => (float) $quantity,
                'unit_price' => (float) $unitPrice,
                'tax_rate' => (float) $taxRate,
                'discount_amount' => (float) $discountAmount,
            ];

            $itemCount++;
        } while ($this->confirm('Add another item?'));

        return $items;
    }

    /**
     * Parse items from string format.
     */
    protected function parseItems(?string $itemsString): ?array
    {
        if (empty($itemsString)) {
            return null;
        }

        $items = [];
        $itemStrings = explode(',', $itemsString);

        foreach ($itemStrings as $itemString) {
            $parts = explode(':', trim($itemString));
            if (count($parts) >= 3) {
                $items[] = [
                    'description' => $parts[0] ?? 'Credit item',
                    'quantity' => (float) ($parts[1] ?? 1),
                    'unit_price' => (float) ($parts[2] ?? 0),
                    'tax_rate' => (float) ($parts[3] ?? 0),
                    'discount_amount' => 0,
                ];
            }
        }

        return empty($items) ? null : $items;
    }

    /**
     * Calculate tax amount from input.
     */
    protected function calculateTaxAmount(array $input): float
    {
        if (! isset($input['tax'])) {
            return 0;
        }

        $amount = (float) $input['amount'];
        $tax = $input['tax'];

        // If tax is a percentage
        if (str_ends_with($tax, '%')) {
            return $amount * ((float) rtrim($tax, '%') / 100);
        }

        // Otherwise, treat as absolute amount
        return (float) $tax;
    }

    /**
     * Find invoice by ID or number.
     */
    protected function findInvoice(string $identifier): Invoice
    {
        // Try by UUID
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $identifier)) {
            $invoice = Invoice::where('id', $identifier)
                ->where('company_id', $this->company->id)
                ->first();
        }

        // Try by invoice number
        if (! isset($invoice)) {
            $invoice = Invoice::where('invoice_number', $identifier)
                ->where('company_id', $this->company->id)
                ->first();
        }

        if (! $invoice) {
            $this->error("Invoice '{$identifier}' not found.");
            exit(1);
        }

        if ($invoice->status !== 'posted') {
            $this->error('Credit notes can only be created for posted invoices.');
            exit(1);
        }

        return $invoice;
    }

    /**
     * Validate credit amount against invoice balance.
     */
    protected function validateCreditAmount(float $amount, Invoice $invoice): void
    {
        if ($amount > $invoice->balance_due) {
            $this->error("Credit amount (\${$amount}) cannot exceed invoice balance due (\${$invoice->balance_due}).");
            exit(1);
        }

        if ($amount <= 0) {
            $this->error('Credit amount must be greater than zero.');
            exit(1);
        }
    }

    /**
     * Display preview of credit note to be created.
     */
    protected function displayPreview(array $data, Invoice $invoice): void
    {
        $this->info('[DRY RUN] Credit Note Preview');
        $this->line(str_repeat('=', 50));
        $this->line("Invoice: {$invoice->invoice_number}");
        $this->line("Customer: {$invoice->customer->name}");
        $this->line("Reason: {$data['reason']}");
        $this->line('Amount: ${'.number_format($data['amount'], 2).'}');
        $this->line('Tax: ${'.number_format($data['tax_amount'], 2).'}');
        $this->line('Total: ${'.number_format($data['total_amount'], 2).'}');
        $this->line("Currency: {$data['currency']}");

        if (! empty($data['items'])) {
            $this->line("\nItems:");
            foreach ($data['items'] as $index => $item) {
                $this->line('  '.($index + 1).". {$item['description']} - {$item['quantity']} x \${$item['unit_price']}");
            }
        }

        if (! empty($data['notes'])) {
            $this->line("\nNotes: {$data['notes']}");
        }

        $this->line(str_repeat('=', 50));
        $this->line('Use without --dry-run to create this credit note.');
    }

    /**
     * Display success message with credit note details.
     */
    protected function displaySuccessMessage(\App\Models\CreditNote $creditNote): void
    {
        $this->info('Credit note created successfully!');
        $this->line("Credit Note Number: {$creditNote->credit_note_number}");
        $this->line("Invoice: {$creditNote->invoice->invoice_number}");
        $this->line('Total Amount: ${'.number_format($creditNote->total_amount, 2).'}');
        $this->line("Status: {$creditNote->status}");
    }
}
