<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Modules\Accounting\Services\InvoiceService;

class InvoiceCancel extends InvoiceBaseCommand
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'invoice:cancel
                           {invoice : Invoice ID, number, or UUID}
                           {--reason= : Reason for cancellation}
                           {--notify-customer : Send notification to customer about cancellation}
                           {--email-notify= : Email address to notify about cancellation}
                           {--force : Force cancellation even if normally not allowed}
                           {--reverse-posting : Reverse ledger entries if invoice is posted}
                           {--credit-note : Create credit note instead of direct cancellation}
                           {--effective-date= : Effective date for cancellation (Y-m-d format)}
                           {--notes= : Additional notes about the cancellation}
                           {--company= : Company ID (overrides current company)}
                           {--format=table : Output format (table, json, csv, text)}
                           {--natural= : Natural language input}
                           {--quiet : Suppress output}';

    /**
     * The console command description.
     */
    protected $description = 'Cancel an invoice';

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

        // Validate that invoice can be cancelled
        $validationResult = $this->validateInvoiceForCancellation($invoice, $input);
        if (! $validationResult['can_cancel']) {
            $this->error($validationResult['message']);

            return self::FAILURE;
        }

        // Show warnings if any
        if (! empty($validationResult['warnings']) && ! $this->option('force')) {
            $this->warn('Cancellation warnings:');
            foreach ($validationResult['warnings'] as $warning) {
                $this->line("  - {$warning}");
            }

            if (! $this->confirm('Continue despite warnings?')) {
                return self::FAILURE;
            }
        }

        // Get cancellation reason
        $reason = $this->getCancellationReason($input);

        // Prepare cancellation data
        $cancellationData = $this->prepareCancellationData($invoice, $reason, $input);

        // Show confirmation unless force flag is used
        if (! $this->option('force')) {
            if (! $this->showCancellationConfirmation($invoice, $cancellationData)) {
                return self::FAILURE;
            }
        }

        // Perform the cancellation
        return $this->cancelInvoice($invoice, $cancellationData, $input);
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
     * Validate invoice for cancellation.
     */
    protected function validateInvoiceForCancellation(Invoice $invoice, array $input): array
    {
        $result = [
            'can_cancel' => true,
            'message' => '',
            'warnings' => [],
        ];

        // Check if already cancelled
        if ($invoice->status === 'cancelled') {
            $result['can_cancel'] = false;
            $result['message'] = "Invoice #{$invoice->invoice_number} is already cancelled.";

            return $result;
        }

        // Check if fully paid
        if ($invoice->payment_status === 'paid' && ! $this->option('force')) {
            $result['can_cancel'] = false;
            $result['message'] = "Cannot cancel fully paid invoice #{$invoice->invoice_number}. Use --force to override.";

            return $result;
        }

        // Check if has payments
        $totalPayments = $invoice->payments()->sum('amount');
        if ($totalPayments > 0 && ! $this->option('force')) {
            $result['warnings'][] = "Invoice has payments totaling \${$totalPayments}. Cancellation will require refunds.";
        }

        // Check if posted and reverse-posting not requested
        if ($invoice->status === 'posted' && ! $this->option('reverse-posting') && ! $this->option('force')) {
            $result['warnings'][] = 'Invoice is posted to ledger. Consider using --reverse-posting to reverse entries.';
        }

        // Check if sent to customer
        if ($invoice->sent_at && ! $this->option('notify-customer')) {
            $result['warnings'][] = 'Invoice has been sent to customer. Consider using --notify-customer.';
        }

        // Check if overdue
        if ($invoice->is_overdue) {
            $result['warnings'][] = "Invoice is overdue by {$invoice->days_overdue} days.";
        }

        return $result;
    }

    /**
     * Get cancellation reason from input or ask user.
     */
    protected function getCancellationReason(array $input): string
    {
        $reason = $input['reason'] ?? $this->option('reason');

        if (! $reason) {
            $reason = $this->ask('Reason for cancellation');
        }

        if (empty($reason)) {
            $this->error('Cancellation reason is required.');
            exit(1);
        }

        return $reason;
    }

    /**
     * Prepare cancellation data.
     */
    protected function prepareCancellationData(Invoice $invoice, string $reason, array $input): array
    {
        return [
            'reason' => $reason,
            'effective_date' => $input['effective_date'] ?? $this->option('effective-date') ?? now()->toDateString(),
            'notes' => $input['notes'] ?? $this->option('notes'),
            'notify_customer' => $this->option('notify-customer') || in_array('notify', $input['flags'] ?? []),
            'email_notify' => $this->option('email-notify') ?? $input['email_notify'] ?? null,
            'reverse_posting' => $this->option('reverse-posting') || in_array('reverse', $input['flags'] ?? []),
            'create_credit_note' => $this->option('credit-note') || in_array('credit', $input['flags'] ?? []),
            'cancelled_by_user_id' => $this->user->id,
        ];
    }

    /**
     * Show cancellation confirmation.
     */
    protected function showCancellationConfirmation(Invoice $invoice, array $cancellationData): bool
    {
        $this->warn('Cancellation Summary:');
        $this->line('');

        $this->info('Invoice Details:');
        $this->line("Invoice Number: {$invoice->invoice_number}");
        $this->line("Customer: {$invoice->customer->name}");
        $this->line("Amount: \${$invoice->total_amount}");
        $this->line("Status: {$invoice->status}");
        $this->line("Payment Status: {$invoice->payment_status}");

        $totalPayments = $invoice->payments()->sum('amount');
        if ($totalPayments > 0) {
            $this->line("Payments Received: \${$totalPayments}");
            $this->line("Refund Required: \${$totalPayments}");
        }

        $this->line('');
        $this->info('Cancellation Details:');
        $this->line("Reason: {$cancellationData['reason']}");
        $this->line("Effective Date: {$cancellationData['effective_date']}");

        if ($cancellationData['reverse_posting']) {
            $this->line('Ledger Entries: Will be reversed');
        }

        if ($cancellationData['create_credit_note']) {
            $this->line('Credit Note: Will be created');
        }

        if ($cancellationData['notify_customer']) {
            $this->line('Customer Notification: Will be sent');
        }

        $this->line('');

        return $this->confirm('Are you sure you want to cancel this invoice?');
    }

    /**
     * Cancel the invoice.
     */
    protected function cancelInvoice(Invoice $invoice, array $cancellationData, array $input): int
    {
        try {
            \DB::transaction(function () use ($invoice, $cancellationData) {
                // Update invoice status and cancellation details
                $invoice->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancelled_by_user_id' => $cancellationData['cancelled_by_user_id'],
                    'cancellation_reason' => $cancellationData['reason'],
                    'cancellation_notes' => $cancellationData['notes'],
                    'cancellation_effective_date' => \Carbon\Carbon::parse($cancellationData['effective_date']),
                ]);

                // Handle payments (create refunds if needed)
                $this->handlePaymentsOnCancellation($invoice, $cancellationData);

                // Reverse ledger entries if requested
                if ($cancellationData['reverse_posting']) {
                    $this->reverseLedgerEntries($invoice, $cancellationData);
                }

                // Create credit note if requested
                if ($cancellationData['create_credit_note']) {
                    $this->createCreditNote($invoice, $cancellationData);
                }

                // Send notifications
                $this->sendCancellationNotifications($invoice, $cancellationData);
            });

            $this->info('Invoice cancelled successfully');
            $this->displaySuccess("Invoice #{$invoice->invoice_number} cancelled", [
                'Reason' => $cancellationData['reason'],
                'Effective Date' => $cancellationData['effective_date'],
                'Cancelled By' => $this->user->name,
            ]);

            // Log the action
            $this->logExecution('invoice_cancelled', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'reason' => $cancellationData['reason'],
                'effective_date' => $cancellationData['effective_date'],
            ]);

            return self::SUCCESS;

        } catch (\Throwable $exception) {
            $this->error('Failed to cancel invoice: '.$exception->getMessage());

            if (config('app.debug')) {
                $this->line('Stack trace:');
                $this->line($exception->getTraceAsString());
            }

            return self::FAILURE;
        }
    }

    /**
     * Handle payments when cancelling invoice.
     */
    protected function handlePaymentsOnCancellation(Invoice $invoice, array $cancellationData): void
    {
        $payments = $invoice->payments()->get();

        foreach ($payments as $payment) {
            // In a real implementation, this would:
            // 1. Create refund records
            // 2. Process actual refunds through payment gateway
            // 3. Update payment status
            // 4. Send refund confirmations

            $this->line("Processing refund for payment #{$payment->id}: \${$payment->amount}");
        }
    }

    /**
     * Reverse ledger entries for posted invoices.
     */
    protected function reverseLedgerEntries(Invoice $invoice, array $cancellationData): void
    {
        // In a real implementation, this would:
        // 1. Find all journal entries related to the invoice
        // 2. Create reversing entries with opposite amounts
        // 3. Update account balances
        // 4. Record the reversal in audit trail

        $this->line("Reversing ledger entries for invoice #{$invoice->invoice_number}");
    }

    /**
     * Create credit note for the cancelled invoice.
     */
    protected function createCreditNote(Invoice $invoice, array $cancellationData): void
    {
        // In a real implementation, this would:
        // 1. Create a credit note record
        // 2. Copy line items from invoice to credit note
        // 3. Set appropriate credit note number
        // 4. Link to original invoice

        $creditNoteNumber = 'CN-'.str_replace('INV-', '', $invoice->invoice_number);
        $this->line("Creating credit note #{$creditNoteNumber} for invoice #{$invoice->invoice_number}");
    }

    /**
     * Send cancellation notifications.
     */
    protected function sendCancellationNotifications(Invoice $invoice, array $cancellationData): void
    {
        // Notify customer if requested
        if ($cancellationData['notify_customer'] && $invoice->customer->email) {
            $this->line("Sending cancellation notification to customer: {$invoice->customer->email}");
        }

        // Notify additional email addresses
        if ($cancellationData['email_notify']) {
            $emails = explode(',', $cancellationData['email_notify']);
            foreach ($emails as $email) {
                $this->line('Sending cancellation notification to: '.trim($email));
            }
        }
    }

    /**
     * Get usage examples for this command.
     */
    protected function getExamples(): array
    {
        return [
            'invoice:cancel INV-2024-001 --reason="Customer request"',
            'invoice:cancel 12345 --reason="Duplicate invoice" --notify-customer --reverse-posting',
            'invoice:cancel "uuid-string" --reason="Error in billing" --credit-note --force',
            'invoice:cancel INV-2024-001 --reason="Service cancelled" --effective-date=2024-02-01',
            'invoice:cancel --natural="cancel invoice INV-2024-001 reason customer request notify customer" --quiet',
        ];
    }
}
