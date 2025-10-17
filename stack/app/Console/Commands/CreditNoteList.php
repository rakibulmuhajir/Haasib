<?php

namespace App\Console\Commands;

class CreditNoteList extends CreditNoteBaseCommand
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'creditnote:list
                           {--status= : Filter by status (draft, posted, cancelled)}
                           {--invoice= : Filter by invoice ID or number}
                           {--customer= : Filter by customer ID or name}
                           {--currency= : Filter by currency}
                           {--amount-from= : Filter by minimum amount}
                           {--amount-to= : Filter by maximum amount}
                           {--date-from= : Filter by date from (Y-m-d)}
                           {--date-to= : Filter by date to (Y-m-d)}
                           {--search= : Search in credit note number, reason}
                           {--limit=50 : Limit number of results}
                           {--sort=created_at : Sort column (created_at, amount, status)}
                           {--order=desc : Sort order (asc, desc)}
                           {--format=table : Output format (table, json, csv, text)}
                           {--export= : Export to file}
                           {--company= : Company ID (overrides current company)}
                           {--summary : Show summary statistics only}
                           {--quiet : Suppress output}';

    /**
     * The console command description.
     */
    protected $description = 'List credit notes for a company';

    /**
     * Handle the command logic.
     */
    protected function handleCommand(): int
    {
        $input = $this->parseInput();

        // Show summary only if requested
        if (isset($input['summary'])) {
            $this->displaySummary($input);

            return self::SUCCESS;
        }

        // Get credit notes
        $perPage = $input['limit'] ?? 50;
        $page = 1; // Could implement pagination later

        $creditNotes = $this->creditNoteService->getCreditNotesForCompany(
            $this->company,
            auth()->user(),
            $this->prepareFilters($input),
            $perPage,
            $page
        );

        if ($creditNotes->isEmpty()) {
            $this->info('No credit notes found matching the criteria.');

            return self::SUCCESS;
        }

        // Display results
        $this->displayCreditNotes($creditNotes, $input);

        // Export if requested
        if (isset($input['export'])) {
            $this->exportCreditNotes($creditNotes, $input['export'], $input);
        }

        return self::SUCCESS;
    }

    /**
     * Display credit notes in the requested format.
     */
    protected function displayCreditNotes($creditNotes, array $input): void
    {
        $format = $input['format'] ?? 'table';

        match ($format) {
            'json' => $this->outputJson($creditNotes),
            'csv' => $this->outputCsv($creditNotes),
            'text' => $this->outputText($creditNotes),
            default => $this->outputTable($creditNotes),
        };
    }

    /**
     * Display credit notes as a table.
     */
    protected function outputTable($creditNotes): void
    {
        $tableData = $creditNotes->getCollection()->map(function ($creditNote) {
            return [
                'Number' => $creditNote->credit_note_number,
                'Invoice' => $creditNote->invoice->invoice_number,
                'Customer' => $creditNote->invoice->customer->name,
                'Reason' => substr($creditNote->reason, 0, 40),
                'Amount' => '$'.number_format($creditNote->total_amount, 2),
                'Status' => ucfirst($creditNote->status),
                'Created' => $creditNote->created_at->format('Y-m-d H:i'),
            ];
        })->toArray();

        $this->table(
            ['Number', 'Invoice', 'Customer', 'Reason', 'Amount', 'Status', 'Created'],
            $tableData
        );

        $this->line('');
        $this->info("Showing {$creditNotes->count()} of {$creditNotes->total()} credit notes");
    }

    /**
     * Display credit notes as JSON.
     */
    protected function outputJson($creditNotes): void
    {
        $data = $creditNotes->getCollection()->map(function ($creditNote) {
            return [
                'id' => $creditNote->id,
                'credit_note_number' => $creditNote->credit_note_number,
                'invoice_id' => $creditNote->invoice_id,
                'invoice_number' => $creditNote->invoice->invoice_number,
                'customer' => [
                    'id' => $creditNote->invoice->customer->id,
                    'name' => $creditNote->invoice->customer->name,
                    'email' => $creditNote->invoice->customer->email,
                ],
                'reason' => $creditNote->reason,
                'amount' => $creditNote->amount,
                'tax_amount' => $creditNote->tax_amount,
                'total_amount' => $creditNote->total_amount,
                'currency' => $creditNote->currency,
                'status' => $creditNote->status,
                'remaining_balance' => $creditNote->remainingBalance(),
                'created_at' => $creditNote->created_at->toISOString(),
                'posted_at' => $creditNote->posted_at?->toISOString(),
                'cancelled_at' => $creditNote->cancelled_at?->toISOString(),
            ];
        })->toArray();

        $this->line(json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * Display credit notes as CSV.
     */
    protected function outputCsv($creditNotes): void
    {
        $headers = ['Number', 'Invoice', 'Customer', 'Reason', 'Amount', 'Tax Amount', 'Total Amount', 'Currency', 'Status', 'Created At'];
        $this->line(implode(',', $headers));

        foreach ($creditNotes->getCollection() as $creditNote) {
            $row = [
                $creditNote->credit_note_number,
                $creditNote->invoice->invoice_number,
                '"'.str_replace('"', '""', $creditNote->invoice->customer->name).'"',
                '"'.str_replace('"', '""', $creditNote->reason).'"',
                $creditNote->total_amount,
                $creditNote->tax_amount,
                $creditNote->total_amount,
                $creditNote->currency,
                $creditNote->status,
                $creditNote->created_at->format('Y-m-d H:i:s'),
            ];
            $this->line(implode(',', $row));
        }
    }

    /**
     * Display credit notes as plain text.
     */
    protected function outputText($creditNotes): void
    {
        foreach ($creditNotes->getCollection() as $creditNote) {
            $this->line(str_repeat('=', 60));
            $this->line("Credit Note: {$creditNote->credit_note_number}");
            $this->line("Invoice: {$creditNote->invoice->invoice_number}");
            $this->line("Customer: {$creditNote->invoice->customer->name}");
            $this->line("Reason: {$creditNote->reason}");
            $this->line('Amount: ${'.number_format($creditNote->total_amount, 2).'}');
            $this->line('Status: '.ucfirst($creditNote->status));
            $this->line("Created: {$creditNote->created_at->format('Y-m-d H:i:s')}");
            $this->line('Remaining Balance: ${'.number_format($creditNote->remainingBalance(), 2).'}');
            $this->line('');
        }
    }

    /**
     * Display summary statistics.
     */
    protected function displaySummary(array $input): void
    {
        $stats = $this->creditNoteService->getCreditNoteStatistics($this->company, auth()->user());

        $this->info('Credit Note Summary');
        $this->line(str_repeat('=', 50));
        $this->line("Total Credit Notes: {$stats['total_credit_notes']}");
        $this->line("Draft: {$stats['draft_credit_notes']}");
        $this->line("Posted: {$stats['posted_credit_notes']}");
        $this->line("Cancelled: {$stats['cancelled_credit_notes']}");
        $this->line('Total Amount Issued: ${'.number_format($stats['total_amount_issued'], 2).'}');
        $this->line('Total Amount Applied: ${'.number_format($stats['total_amount_applied'], 2).'}');

        if (! empty($stats['credit_notes_by_currency'])) {
            $this->line('');
            $this->info('By Currency:');
            foreach ($stats['credit_notes_by_currency'] as $currency => $data) {
                $this->line("  {$currency}: {$data['count']} credit notes, \${".number_format($data['total'], 2).'}');
            }
        }

        if (! empty($stats['recently_created'])) {
            $this->line('');
            $this->info('Recently Created:');
            foreach ($stats['recently_created'] as $creditNote) {
                $this->line("  {$creditNote->credit_note_number} - \${".number_format($creditNote->total_amount, 2)."} - {$creditNote->status}");
            }
        }
    }

    /**
     * Export credit notes to file.
     */
    protected function exportCreditNotes($creditNotes, string $filename, array $input): void
    {
        $format = $input['format'] ?? 'csv';
        $data = '';

        switch ($format) {
            case 'json':
                $data = json_encode($creditNotes->getCollection()->map(function ($creditNote) {
                    return $creditNote->toArray();
                })->toArray(), JSON_PRETTY_PRINT);
                break;
            case 'csv':
                $data = $this->generateCsvData($creditNotes);
                break;
            case 'text':
                $data = $this->generateTextData($creditNotes);
                break;
        }

        // Ensure filename has proper extension
        if (! preg_match('/\.(json|csv|txt)$/i', $filename)) {
            $filename .= '.'.$format;
        }

        file_put_contents($filename, $data);
        $this->info("Exported to: {$filename}");
    }

    /**
     * Generate CSV data for export.
     */
    protected function generateCsvData($creditNotes): string
    {
        $output = "Credit Note Number,Invoice Number,Customer,Reason,Amount,Tax Amount,Total Amount,Currency,Status,Created At\n";

        foreach ($creditNotes->getCollection() as $creditNote) {
            $output .= "{$creditNote->credit_note_number},";
            $output .= "{$creditNote->invoice->invoice_number},";
            $output .= '"'.str_replace('"', '""', $creditNote->invoice->customer->name)."',";
            $output .= '"'.str_replace('"', '""', $creditNote->reason)."',";
            $output .= "{$creditNote->total_amount},";
            $output .= "{$creditNote->tax_amount},";
            $output .= "{$creditNote->total_amount},";
            $output .= "{$creditNote->currency},";
            $output .= "{$creditNote->status},";
            $output .= "{$creditNote->created_at->format('Y-m-d H:i:s')}\n";
        }

        return $output;
    }

    /**
     * Generate text data for export.
     */
    protected function generateTextData($creditNotes): string
    {
        $output = "Credit Notes Export\n";
        $output .= 'Generated: '.now()->format('Y-m-d H:i:s')."\n";
        $output .= "Company: {$this->company->name}\n";
        $output .= str_repeat('=', 50)."\n\n";

        foreach ($creditNotes->getCollection() as $creditNote) {
            $output .= "Credit Note: {$creditNote->credit_note_number}\n";
            $output .= "Invoice: {$creditNote->invoice->invoice_number}\n";
            $output .= "Customer: {$creditNote->invoice->customer->name}\n";
            $output .= "Reason: {$creditNote->reason}\n";
            $output .= 'Amount: ${'.number_format($creditNote->total_amount, 2)."}\n";
            $output .= 'Status: '.ucfirst($creditNote->status)."\n";
            $output .= "Created: {$creditNote->created_at->format('Y-m-d H:i:s')}\n";
            $output .= str_repeat('-', 30)."\n";
        }

        return $output;
    }

    /**
     * Prepare filters from input.
     */
    protected function prepareFilters(array $input): array
    {
        $filters = [];

        if (isset($input['status'])) {
            $filters['status'] = $input['status'];
        }

        if (isset($input['invoice'])) {
            // Try to find invoice by ID or number
            $invoice = $this->findInvoice($input['invoice']);
            $filters['invoice_id'] = $invoice->id;
        }

        if (isset($input['customer'])) {
            // Try to find customer by ID or name
            $filters['customer_id'] = $this->findCustomer($input['customer']);
        }

        if (isset($input['currency'])) {
            $filters['currency'] = strtoupper($input['currency']);
        }

        if (isset($input['amount_from'])) {
            $filters['amount_from'] = $input['amount_from'];
        }

        if (isset($input['amount_to'])) {
            $filters['amount_to'] = $input['amount_to'];
        }

        if (isset($input['date_from'])) {
            $filters['date_from'] = $input['date_from'];
        }

        if (isset($input['date_to'])) {
            $filters['date_to'] = $input['date_to'];
        }

        if (isset($input['search'])) {
            $filters['search'] = $input['search'];
        }

        return $filters;
    }

    /**
     * Find invoice by ID or number.
     */
    protected function findInvoice(string $identifier)
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

        return $invoice;
    }

    /**
     * Find customer by ID or name.
     */
    protected function findCustomer(string $identifier)
    {
        // Try by UUID
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $identifier)) {
            $customer = \App\Models\Customer::where('id', $identifier)
                ->where('company_id', $this->company->id)
                ->first();
        }

        // Try by name
        if (! isset($customer)) {
            $customer = \App\Models\Customer::where('name', 'ilike', "%{$identifier}%")
                ->where('company_id', $this->company->id)
                ->first();
        }

        if (! $customer) {
            $this->error("Customer '{$identifier}' not found.");
            exit(1);
        }

        return $customer->id;
    }
}
