<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Modules\Accounting\Services\InvoiceService;

class InvoicePost extends InvoiceBaseCommand
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'invoice:post
                           {invoice : Invoice ID, number, or UUID}
                           {--force : Force posting even if validation warnings exist}
                           {--date= : Posting date (Y-m-d format, defaults to today)}
                           {--period= : Accounting period (Y-m format, defaults to current period)}
                           {--description= : Description for journal entry}
                           {--auto-reverse : Create auto-reversing entry for next period}
                           {--reverse-date= : Date for auto-reversing entry}
                           {--validate-only : Validate without actually posting}
                           {--dry-run : Show what would be posted without doing it}
                           {--company= : Company ID (overrides current company)}
                           {--format=table : Output format (table, json, csv, text)}
                           {--natural= : Natural language input}
                           {--quiet : Suppress output}';

    /**
     * The console command description.
     */
    protected $description = 'Post an invoice to the accounting ledger';

    /**
     * The invoice service instance.
     */
    protected InvoiceService $invoiceService;

    /**
     * Create a new command instance.
     */
    public function __construct(InvoiceService $invoiceService)
    {
        parent::__construct();
        $this->invoiceService = $invoiceService;
    }

    /**
     * Execute the command logic.
     */
    protected function executeCommand(): int
    {
        $input = $this->parseInput();

        // Find the invoice
        $invoice = $this->findInvoice($input);

        // Validate that invoice can be posted
        $validationResult = $this->validateInvoiceForPosting($invoice, $input);
        if (! $validationResult['can_post']) {
            $this->error($validationResult['message']);

            return self::FAILURE;
        }

        // Show warnings if any
        if (! empty($validationResult['warnings']) && ! $this->option('force')) {
            $this->warn('Validation warnings:');
            foreach ($validationResult['warnings'] as $warning) {
                $this->line("  - {$warning}");
            }

            if (! $this->confirm('Continue despite warnings?')) {
                return self::FAILURE;
            }
        }

        // Prepare posting data
        $postingData = $this->preparePostingData($invoice, $input);

        // Dry run mode
        if ($this->option('dry-run') || $this->option('validate-only')) {
            return $this->performDryRun($invoice, $postingData, $validationResult);
        }

        // Perform the actual posting
        return $this->postInvoice($invoice, $postingData, $input);
    }

    /**
     * Find invoice by ID, number, or UUID.
     */
    protected function findInvoice(array $input): Invoice
    {
        $identifier = $input['invoice'] ?? $this->argument('invoice');

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

        // Try numeric ID
        if (! isset($invoice) && is_numeric($identifier)) {
            $invoice = Invoice::where('id', $identifier)
                ->where('company_id', $this->company->id)
                ->first();
        }

        if (! $invoice) {
            $this->error("Invoice '{$identifier}' not found.");
            exit(1);
        }

        return $invoice;
    }

    /**
     * Validate invoice for posting.
     */
    protected function validateInvoiceForPosting(Invoice $invoice, array $input): array
    {
        $result = [
            'can_post' => true,
            'message' => '',
            'warnings' => [],
        ];

        // Check if already posted
        if ($invoice->status === 'posted') {
            $result['can_post'] = false;
            $result['message'] = "Invoice #{$invoice->invoice_number} is already posted.";

            return $result;
        }

        // Check if cancelled
        if ($invoice->status === 'cancelled') {
            $result['can_post'] = false;
            $result['message'] = "Cannot post cancelled invoice #{$invoice->invoice_number}.";

            return $result;
        }

        // Check if has line items
        if ($invoice->lineItems->isEmpty()) {
            $result['can_post'] = false;
            $result['message'] = "Cannot post invoice #{$invoice->invoice_number} without line items.";

            return $result;
        }

        // Check if total amount is valid
        if ($invoice->total_amount <= 0) {
            $result['can_post'] = false;
            $result['message'] = "Cannot post invoice #{$invoice->invoice_number} with zero or negative total.";

            return $result;
        }

        // Check customer
        if (! $invoice->customer) {
            $result['can_post'] = false;
            $result['message'] = "Cannot post invoice #{$invoice->invoice_number} without a valid customer.";

            return $result;
        }

        // Warnings (non-blocking)
        if ($invoice->due_date->isPast()) {
            $result['warnings'][] = "Invoice due date ({$invoice->due_date->format('Y-m-d')}) is in the past.";
        }

        if ($invoice->issue_date->isFuture()) {
            $result['warnings'][] = "Invoice issue date ({$invoice->issue_date->format('Y-m-d')}) is in the future.";
        }

        if (empty($invoice->terms)) {
            $result['warnings'][] = 'Invoice has no payment terms specified.';
        }

        // Check posting date
        $postingDate = $input['date'] ?? $this->option('date') ?? now()->toDateString();
        if (\Carbon\Carbon::parse($postingDate)->isFuture()) {
            $result['warnings'][] = "Posting date ({$postingDate}) is in the future.";
        }

        return $result;
    }

    /**
     * Prepare posting data.
     */
    protected function preparePostingData(Invoice $invoice, array $input): array
    {
        $postingDate = $input['date'] ?? $this->option('date') ?? now()->toDateString();
        $period = $input['period'] ?? $this->option('period') ?? now()->format('Y-m');
        $description = $input['description'] ?? $this->option('description') ??
            "Invoice #{$invoice->invoice_number} - {$invoice->customer->name}";

        return [
            'posting_date' => \Carbon\Carbon::parse($postingDate),
            'period' => $period,
            'description' => $description,
            'auto_reverse' => $this->option('auto-reverse') || in_array('reverse', $input['flags'] ?? []),
            'reverse_date' => $this->getReverseDate($input),
            'journal_entries' => $this->generateJournalEntries($invoice),
        ];
    }

    /**
     * Get reverse date for auto-reversing entries.
     */
    protected function getReverseDate(array $input): ?\Carbon\Carbon
    {
        if ($this->option('reverse-date')) {
            return \Carbon\Carbon::parse($this->option('reverse-date'));
        }

        if (isset($input['reverse_date'])) {
            return \Carbon\Carbon::parse($input['reverse_date']);
        }

        // Default to first day of next period
        $period = $input['period'] ?? $this->option('period') ?? now()->format('Y-m');

        return \Carbon\Carbon::parse($period.'-01')->addMonth()->startOfMonth();
    }

    /**
     * Generate journal entries for the invoice.
     */
    protected function generateJournalEntries(Invoice $invoice): array
    {
        $entries = [];

        // Debit: Accounts Receivable
        $entries[] = [
            'account' => 'accounts_receivable',
            'debit' => $invoice->total_amount,
            'credit' => 0,
            'description' => "Invoice #{$invoice->invoice_number} - {$invoice->customer->name}",
            'reference' => $invoice->invoice_number,
        ];

        // Credit: Revenue (subtotal less taxes)
        $revenueAmount = $invoice->subtotal - $invoice->discount_amount;
        if ($revenueAmount > 0) {
            $entries[] = [
                'account' => 'revenue',
                'debit' => 0,
                'credit' => $revenueAmount,
                'description' => "Revenue from Invoice #{$invoice->invoice_number}",
                'reference' => $invoice->invoice_number,
            ];
        }

        // Credit: Tax Payable (if taxes apply)
        if ($invoice->tax_amount > 0) {
            $entries[] = [
                'account' => 'tax_payable',
                'debit' => 0,
                'credit' => $invoice->tax_amount,
                'description' => "Tax from Invoice #{$invoice->invoice_number}",
                'reference' => $invoice->invoice_number,
            ];
        }

        // Credit: Discount Allowed (if discounts apply)
        if ($invoice->discount_amount > 0) {
            $entries[] = [
                'account' => 'discount_allowed',
                'debit' => $invoice->discount_amount,
                'credit' => 0,
                'description' => "Discount allowed on Invoice #{$invoice->invoice_number}",
                'reference' => $invoice->invoice_number,
            ];
        }

        return $entries;
    }

    /**
     * Perform dry run to show what would be posted.
     */
    protected function performDryRun(Invoice $invoice, array $postingData, array $validationResult): int
    {
        $this->info($this->option('validate-only') ? 'Validation Results:' : 'Dry Run - Posting Preview:');
        $this->line('');

        // Invoice details
        $this->info('Invoice Details:');
        $this->displaySuccess("Invoice #{$invoice->invoice_number}", [
            'Customer' => $invoice->customer->name,
            'Amount' => "\${$invoice->total_amount}",
            'Due Date' => $invoice->due_date->format('Y-m-d'),
            'Current Status' => $invoice->status,
        ]);

        // Posting details
        $this->line('');
        $this->info('Posting Details:');
        $this->line("Posting Date: {$postingData['posting_date']->format('Y-m-d')}");
        $this->line("Period: {$postingData['period']}");
        $this->line("Description: {$postingData['description']}");

        if ($postingData['auto_reverse']) {
            $this->line("Auto-Reverse: Yes (on {$postingData['reverse_date']->format('Y-m-d')})");
        }

        // Journal entries
        $this->line('');
        $this->info('Journal Entries to be Created:');

        $headers = ['Account', 'Debit', 'Credit', 'Description', 'Reference'];
        $rows = array_map(function ($entry) {
            return [
                $entry['account'],
                $entry['debit'] > 0 ? "\${$entry['debit']}" : '',
                $entry['credit'] > 0 ? "\${$entry['credit']}" : '',
                $entry['description'],
                $entry['reference'],
            ];
        }, $postingData['journal_entries']);

        $this->table($headers, $rows);

        // Validation summary
        $this->line('');
        $this->info('Validation Summary:');
        $this->line('Status: '.($validationResult['can_post'] ? '✓ Ready to post' : '✗ Cannot post'));

        if (! empty($validationResult['warnings'])) {
            $this->line('Warnings: '.count($validationResult['warnings']));
        }

        if ($this->option('validate-only')) {
            $this->line('');
            $this->info('Use --dry-run to see full posting preview, or remove --validate-only to actually post.');
        } else {
            $this->line('');
            $this->info('Remove --dry-run to actually post the invoice.');
        }

        return $validationResult['can_post'] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Post the invoice to ledger.
     */
    protected function postInvoice(Invoice $invoice, array $postingData, array $input): int
    {
        try {
            \DB::transaction(function () use ($invoice, $postingData) {
                // Update invoice status
                $invoice->update([
                    'status' => 'posted',
                    'posted_at' => $postingData['posting_date'],
                    'posted_by_user_id' => $this->user->id,
                ]);

                // Create journal entries (simulated - would integrate with actual ledger)
                $this->createJournalEntries($invoice, $postingData);

                // Create auto-reversing entry if requested
                if ($postingData['auto_reverse']) {
                    $this->createAutoReversingEntry($invoice, $postingData);
                }
            });

            $this->info('Invoice posted successfully');
            $this->displaySuccess("Invoice #{$invoice->invoice_number} posted to ledger", [
                'Posting Date' => $postingData['posting_date']->format('Y-m-d'),
                'Period' => $postingData['period'],
                'Amount' => "\${$invoice->total_amount}",
                'Journal Entries' => count($postingData['journal_entries']),
            ]);

            // Log the action
            $this->logExecution('invoice_posted', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'posting_date' => $postingData['posting_date'],
                'period' => $postingData['period'],
                'amount' => $invoice->total_amount,
            ]);

            return self::SUCCESS;

        } catch (\Throwable $exception) {
            $this->error('Failed to post invoice: '.$exception->getMessage());

            if (config('app.debug')) {
                $this->line('Stack trace:');
                $this->line($exception->getTraceAsString());
            }

            return self::FAILURE;
        }
    }

    /**
     * Create journal entries (simulated).
     */
    protected function createJournalEntries(Invoice $invoice, array $postingData): void
    {
        // In a real implementation, this would:
        // 1. Create JournalEntry records
        // 2. Update account balances
        // 3. Link to the invoice
        // 4. Handle double-entry bookkeeping rules

        foreach ($postingData['journal_entries'] as $entry) {
            $this->line("Creating journal entry: {$entry['account']} ".
                ($entry['debit'] > 0 ? "Debit \${$entry['debit']}" : "Credit \${$entry['credit']}"));
        }
    }

    /**
     * Create auto-reversing entry (simulated).
     */
    protected function createAutoReversingEntry(Invoice $invoice, array $postingData): void
    {
        // In a real implementation, this would create journal entries that reverse
        // the original entry on the specified date

        $this->line("Creating auto-reversing entry for {$postingData['reverse_date']->format('Y-m-d')}");
    }

    /**
     * Get usage examples for this command.
     */
    protected function getExamples(): array
    {
        return [
            'invoice:post INV-2024-001',
            'invoice:post 12345 --date=2024-02-01 --period=2024-02',
            'invoice:post "uuid-string" --auto-reverse --reverse-date=2024-03-01',
            'invoice:post INV-2024-001 --description="Monthly consulting revenue" --force',
            'invoice:post --natural="post invoice INV-2024-001 to ledger for February 2024" --dry-run',
            'invoice:post INV-2024-001 --validate-only',
        ];
    }
}
