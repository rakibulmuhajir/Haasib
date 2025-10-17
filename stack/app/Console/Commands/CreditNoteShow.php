<?php

namespace App\Console\Commands;

class CreditNoteShow extends CreditNoteBaseCommand
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'creditnote:show
                           {identifier : Credit note ID, number, or partial match}
                           {--format=table : Output format (table, json)}
                           {--items : Show credit note items}
                           {--applications : Show application history}
                           {--emails : Show email history}
                           {--company= : Company ID (overrides current company)}
                           {--export= : Export to file}';

    /**
     * The console command description.
     */
    protected $description = 'Show detailed information about a credit note';

    /**
     * Handle the command logic.
     */
    protected function handleCommand(): int
    {
        $input = $this->parseInput();

        // Find the credit note
        $creditNote = $this->findCreditNote($input['identifier']);

        // Display credit note details
        $this->displayCreditNoteDetails($creditNote, $input);

        // Show items if requested
        if (isset($input['items'])) {
            $this->displayCreditNoteItems($creditNote);
        }

        // Show application history if requested
        if (isset($input['applications'])) {
            $this->displayApplicationHistory($creditNote);
        }

        // Show email history if requested
        if (isset($input['emails'])) {
            $this->displayEmailHistory($creditNote);
        }

        // Export if requested
        if (isset($input['export'])) {
            $this->exportCreditNoteDetails($creditNote, $input['export'], $input);
        }

        return self::SUCCESS;
    }

    /**
     * Display detailed credit note information.
     */
    protected function displayCreditNoteDetails($creditNote, array $input): void
    {
        $format = $input['format'] ?? 'table';

        match ($format) {
            'json' => $this->outputJsonDetails($creditNote),
            default => $this->outputTableDetails($creditNote),
        };
    }

    /**
     * Display credit note details as a table.
     */
    protected function outputTableDetails($creditNote): void
    {
        $this->info('Credit Note Details');
        $this->line(str_repeat('=', 50));

        // Basic information
        $this->line("Credit Note Number: {$creditNote->credit_note_number}");
        $this->line('Status: '.ucfirst($creditNote->status));
        $this->line("Invoice: {$creditNote->invoice->invoice_number}");
        $this->line("Customer: {$creditNote->invoice->customer->name}");
        $this->line("Reason: {$creditNote->reason}");

        $this->line('');
        $this->info('Financial Details:');
        $this->line(str_repeat('-', 20));
        $this->line('Amount: $'.number_format($creditNote->amount, 2));
        $this->line('Tax Amount: $'.number_format($creditNote->tax_amount, 2));
        $this->line('Total Amount: $'.number_format($creditNote->total_amount, 2));
        $this->line("Currency: {$creditNote->currency}");
        $this->line('Remaining Balance: $'.number_format($creditNote->remainingBalance(), 2));

        $this->line('');
        $this->info('Dates:');
        $this->line(str_repeat('-', 10));
        $this->line('Created: '.$creditNote->created_at->format('Y-m-d H:i:s'));

        if ($creditNote->posted_at) {
            $this->line('Posted: '.$creditNote->posted_at->format('Y-m-d H:i:s'));
        }

        if ($creditNote->cancelled_at) {
            $this->line('Cancelled: '.$creditNote->cancelled_at->format('Y-m-d H:i:s'));
            $this->line("Cancellation Reason: {$creditNote->cancellation_reason}");
        }

        // Notes and terms
        if ($creditNote->notes) {
            $this->line('');
            $this->info('Notes:');
            $this->line(str_repeat('-', 10));
            $this->line($creditNote->notes);
        }

        if ($creditNote->terms) {
            $this->line('');
            $this->info('Terms:');
            $this->line(str_repeat('-', 10));
            $this->line($creditNote->terms);
        }

        // Invoice information
        $this->line('');
        $this->info('Related Invoice:');
        $this->line(str_repeat('-', 20));
        $this->line("Invoice Number: {$creditNote->invoice->invoice_number}");
        $this->line('Invoice Status: '.ucfirst($creditNote->invoice->status));
        $this->line('Invoice Total: $'.number_format($creditNote->invoice->total_amount, 2));
        $this->line('Invoice Balance Due: $'.number_format($creditNote->invoice->balance_due, 2));

        // Customer information
        $this->line('');
        $this->info('Customer Information:');
        $this->line(str_repeat('-', 22));
        $this->line("Name: {$creditNote->invoice->customer->name}");
        $this->line("Email: {$creditNote->invoice->customer->email}");

        if ($creditNote->invoice->customer->phone) {
            $this->line("Phone: {$creditNote->invoice->customer->phone}");
        }

        if ($creditNote->invoice->customer->address) {
            $this->line("Address: {$creditNote->invoice->customer->address}");
        }
    }

    /**
     * Display credit note details as JSON.
     */
    protected function outputJsonDetails($creditNote): void
    {
        $data = [
            'id' => $creditNote->id,
            'credit_note_number' => $creditNote->credit_note_number,
            'status' => $creditNote->status,
            'reason' => $creditNote->reason,
            'amount' => $creditNote->amount,
            'tax_amount' => $creditNote->tax_amount,
            'total_amount' => $creditNote->total_amount,
            'currency' => $creditNote->currency,
            'remaining_balance' => $creditNote->remainingBalance(),
            'notes' => $creditNote->notes,
            'terms' => $creditNote->terms,
            'created_at' => $creditNote->created_at->toISOString(),
            'posted_at' => $creditNote->posted_at?->toISOString(),
            'cancelled_at' => $creditNote->cancelled_at?->toISOString(),
            'cancellation_reason' => $creditNote->cancellation_reason,
            'invoice' => [
                'id' => $creditNote->invoice->id,
                'invoice_number' => $creditNote->invoice->invoice_number,
                'status' => $creditNote->invoice->status,
                'total_amount' => $creditNote->invoice->total_amount,
                'balance_due' => $creditNote->invoice->balance_due,
            ],
            'customer' => [
                'id' => $creditNote->invoice->customer->id,
                'name' => $creditNote->invoice->customer->name,
                'email' => $creditNote->invoice->customer->email,
                'phone' => $creditNote->invoice->customer->phone,
                'address' => $creditNote->invoice->customer->address,
            ],
            'creator' => [
                'id' => $creditNote->creator->id,
                'name' => $creditNote->creator->name,
                'email' => $creditNote->creator->email,
            ],
        ];

        $this->line(json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * Display application history.
     */
    protected function displayApplicationHistory($creditNote): void
    {
        $applications = $creditNote->applications()->with(['user'])->get();

        if ($applications->isEmpty()) {
            $this->line('');
            $this->info('Application History:');
            $this->line(str_repeat('-', 20));
            $this->line('No applications found.');

            return;
        }

        $this->line('');
        $this->info('Application History:');
        $this->line(str_repeat('-', 20));

        $tableData = $applications->map(function ($application) {
            return [
                'Date' => $application->created_at->format('Y-m-d H:i'),
                'Amount' => '$'.number_format($application->amount_applied, 2),
                'Applied By' => $application->user->name,
                'Invoice Balance Before' => '$'.number_format($application->invoice_balance_before, 2),
                'Invoice Balance After' => '$'.number_format($application->invoice_balance_after, 2),
                'Notes' => $application->notes ?? '',
            ];
        })->toArray();

        $this->table(
            ['Date', 'Amount', 'Applied By', 'Balance Before', 'Balance After', 'Notes'],
            $tableData
        );

        $totalApplied = $applications->sum('amount_applied');
        $this->line('');
        $this->line('Total Applied: $'.number_format($totalApplied, 2));
        $this->line('Remaining Balance: $'.number_format($creditNote->remainingBalance(), 2));
    }

    /**
     * Export credit note details to file.
     */
    protected function exportCreditNoteDetails($creditNote, string $filename, array $input): void
    {
        $format = $input['format'] ?? 'json';
        $data = '';

        switch ($format) {
            case 'json':
                $data = json_encode([
                    'credit_note' => $creditNote->toArray(),
                    'invoice' => $creditNote->invoice->toArray(),
                    'customer' => $creditNote->invoice->customer->toArray(),
                    'items' => $creditNote->items->toArray(),
                    'applications' => $creditNote->applications->toArray(),
                ], JSON_PRETTY_PRINT);
                break;

            case 'text':
                $data = $this->generateTextExport($creditNote);
                break;
        }

        // Ensure filename has proper extension
        if (! preg_match('/\.(json|txt)$/i', $filename)) {
            $filename .= '.'.$format;
        }

        file_put_contents($filename, $data);
        $this->info("Exported to: {$filename}");
    }

    /**
     * Generate text export for credit note details.
     */
    protected function generateTextExport($creditNote): string
    {
        $output = "Credit Note Details Export\n";
        $output .= 'Generated: '.now()->format('Y-m-d H:i:s')."\n";
        $output .= "Company: {$this->company->name}\n";
        $output .= str_repeat('=', 50)."\n\n";

        $output .= "Credit Note Information:\n";
        $output .= str_repeat('-', 30)."\n";
        $output .= "Credit Note Number: {$creditNote->credit_note_number}\n";
        $output .= 'Status: '.ucfirst($creditNote->status)."\n";
        $output .= "Reason: {$creditNote->reason}\n";
        $output .= 'Amount: $'.number_format($creditNote->amount, 2)."\n";
        $output .= 'Tax Amount: $'.number_format($creditNote->tax_amount, 2)."\n";
        $output .= 'Total Amount: $'.number_format($creditNote->total_amount, 2)."\n";
        $output .= "Currency: {$creditNote->currency}\n";
        $output .= 'Created: '.$creditNote->created_at->format('Y-m-d H:i:s')."\n";

        if ($creditNote->posted_at) {
            $output .= 'Posted: '.$creditNote->posted_at->format('Y-m-d H:i:s')."\n";
        }

        if ($creditNote->cancelled_at) {
            $output .= 'Cancelled: '.$creditNote->cancelled_at->format('Y-m-d H:i:s')."\n";
            $output .= "Cancellation Reason: {$creditNote->cancellation_reason}\n";
        }

        $output .= "\nInvoice Information:\n";
        $output .= str_repeat('-', 20)."\n";
        $output .= "Invoice Number: {$creditNote->invoice->invoice_number}\n";
        $output .= "Customer: {$creditNote->invoice->customer->name}\n";
        $output .= 'Invoice Total: $'.number_format($creditNote->invoice->total_amount, 2)."\n";
        $output .= 'Balance Due: $'.number_format($creditNote->invoice->balance_due, 2)."\n";

        if ($creditNote->items->isNotEmpty()) {
            $output .= "\nItems:\n";
            $output .= str_repeat('-', 10)."\n";
            foreach ($creditNote->items as $index => $item) {
                $output .= ($index + 1).". {$item->description}\n";
                $output .= "   Quantity: {$item->quantity} x $".number_format($item->unit_price, 2)."\n";
                $output .= '   Total: $'.number_format($item->total_amount, 2)."\n";
            }
        }

        if ($creditNote->applications->isNotEmpty()) {
            $output .= "\nApplications:\n";
            $output .= str_repeat('-', 15)."\n";
            foreach ($creditNote->applications as $application) {
                $output .= 'Date: '.$application->created_at->format('Y-m-d H:i:s')."\n";
                $output .= 'Amount: $'.number_format($application->amount_applied, 2)."\n";
                $output .= "Applied By: {$application->user->name}\n";
                $output .= str_repeat('-', 15)."\n";
            }
        }

        return $output;
    }

    /**
     * Display email history for the credit note.
     */
    protected function displayEmailHistory($creditNote): void
    {
        $this->line('');
        $this->info('Email History:');
        $this->line(str_repeat('-', 15));

        // Get email history from activity log
        $emailActivities = \DB::table('activity_log')
            ->where('subject_type', 'App\Models\CreditNote')
            ->where('subject_id', $creditNote->id)
            ->where('description', 'like', '%email%')
            ->orderBy('created_at', 'desc')
            ->get();

        // Get scheduled emails
        $scheduledEmails = \DB::table('acct.scheduled_credit_note_emails')
            ->where('credit_note_id', $creditNote->id)
            ->orderBy('created_at', 'desc')
            ->get();

        if ($emailActivities->isEmpty() && $scheduledEmails->isEmpty()) {
            $this->line('No email history found.');

            return;
        }

        $tableData = [];

        // Add sent emails from activity log
        foreach ($emailActivities as $activity) {
            $properties = json_decode($activity->properties, true) ?? [];

            $tableData[] = [
                'Date' => $activity->created_at->format('Y-m-d H:i'),
                'Type' => 'Sent',
                'Recipient' => $properties['recipient'] ?? 'Unknown',
                'Subject' => $properties['subject'] ?? 'Credit Note',
                'Status' => $properties['result']['success'] ?? true ? '✅ Success' : '❌ Failed',
                'User' => $activity->causer_type === 'App\Models\User' ?
                         \App\Models\User::find($activity->causer_id)?->name ?? 'System' : 'System',
            ];
        }

        // Add scheduled emails
        foreach ($scheduledEmails as $scheduled) {
            $status = match ($scheduled->status) {
                'scheduled' => '⏰ Scheduled',
                'sent' => '✅ Sent',
                'failed' => '❌ Failed',
                default => '❓ Unknown',
            };

            $tableData[] = [
                'Date' => $scheduled->created_at->format('Y-m-d H:i'),
                'Type' => 'Scheduled',
                'Recipient' => $scheduled->recipient_email,
                'Subject' => 'Credit Note',
                'Status' => $status,
                'User' => \App\Models\User::find($scheduled->user_id)?->name ?? 'System',
            ];
        }

        $this->table(
            ['Date', 'Type', 'Recipient', 'Subject', 'Status', 'User'],
            $tableData
        );

        // Show summary
        $totalSent = $emailActivities->count();
        $totalScheduled = $scheduledEmails->where('status', 'scheduled')->count();
        $totalFailed = $scheduledEmails->where('status', 'failed')->count();

        $this->line('');
        $this->line("Total Sent: {$totalSent}");
        $this->line("Scheduled: {$totalScheduled}");
        $this->line("Failed: {$totalFailed}");
    }
}
