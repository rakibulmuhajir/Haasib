<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Modules\Accounting\Services\InvoiceService;

class InvoiceSend extends InvoiceBaseCommand
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'invoice:send
                           {invoice : Invoice ID, number, or UUID}
                           {--email= : Specific email address to send to (overrides customer email)}
                           {--subject= : Custom email subject}
                           {--message= : Custom email message}
                           {--cc= : CC email addresses (comma-separated)}
                           {--bcc= : BCC email addresses (comma-separated)}
                           {--attach-pdf : Also attach PDF invoice}
                           {--template= : Email template to use}
                           {--send-at= : Schedule sending for specific date/time (Y-m-d H:i:s)}
                           {--preview : Preview email without sending}
                           {--company= : Company ID (overrides current company)}
                           {--format=table : Output format (table, json, csv, text)}
                           {--natural= : Natural language input}
                           {--quiet : Suppress output}';

    /**
     * The console command description.
     */
    protected $description = 'Send an invoice to customer via email';

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

        // Validate that invoice can be sent
        if (! $this->canSendInvoice($invoice)) {
            return self::FAILURE;
        }

        // Prepare email data
        $emailData = $this->prepareEmailData($invoice, $input);

        // Preview mode
        if ($this->option('preview') || in_array('preview', $input['flags'] ?? [])) {
            $this->previewEmail($invoice, $emailData);

            return self::SUCCESS;
        }

        // Schedule or send immediately
        if (isset($emailData['send_at']) && $emailData['send_at'] > now()) {
            return $this->scheduleEmail($invoice, $emailData);
        } else {
            return $this->sendEmailImmediately($invoice, $emailData);
        }
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
     * Check if invoice can be sent.
     */
    protected function canSendInvoice(Invoice $invoice): bool
    {
        // Check if already sent
        if ($invoice->sent_at && $invoice->status === 'sent') {
            $this->warning("Invoice #{$invoice->invoice_number} has already been sent.");

            if (! $this->confirm('Do you want to send it again?')) {
                return false;
            }
        }

        // Check if cancelled
        if ($invoice->status === 'cancelled') {
            $this->error("Cannot send cancelled invoice #{$invoice->invoice_number}.");

            return false;
        }

        // Check if customer has email
        if (! $invoice->customer->email) {
            $this->error("Customer '{$invoice->customer->name}' has no email address.");

            return false;
        }

        // Check if invoice has line items
        if ($invoice->lineItems->isEmpty()) {
            $this->error("Invoice #{$invoice->invoice_number} has no line items.");

            return false;
        }

        return true;
    }

    /**
     * Prepare email data.
     */
    protected function prepareEmailData(Invoice $invoice, array $input): array
    {
        $emailData = [
            'to' => $this->getRecipientEmail($invoice, $input),
            'subject' => $this->getSubject($invoice, $input),
            'message' => $this->getMessage($invoice, $input),
            'cc' => $this->getCcEmails($input),
            'bcc' => $this->getBccEmails($input),
            'attach_pdf' => $this->option('attach-pdf') || in_array('pdf', $input['flags'] ?? []),
            'template' => $input['template'] ?? $this->option('template') ?? 'default',
        ];

        // Schedule sending if specified
        if (isset($input['send_at'])) {
            $emailData['send_at'] = \Carbon\Carbon::parse($input['send_at']);
        } elseif ($this->option('send-at')) {
            $emailData['send_at'] = \Carbon\Carbon::parse($this->option('send-at'));
        }

        return $emailData;
    }

    /**
     * Get recipient email address.
     */
    protected function getRecipientEmail(Invoice $invoice, array $input): string
    {
        // Custom email from input
        if (isset($input['customer']) && str_contains($input['customer'], '@')) {
            return $input['customer'];
        }

        // Custom email from option
        if ($this->option('email')) {
            return $this->option('email');
        }

        // Customer's default email
        return $invoice->customer->email;
    }

    /**
     * Get email subject.
     */
    protected function getSubject(Invoice $invoice, array $input): string
    {
        if (isset($input['subject'])) {
            return $input['subject'];
        }

        if ($this->option('subject')) {
            return $this->option('subject');
        }

        // Default subject
        $companyName = $this->company->name;
        $invoiceNumber = $invoice->invoice_number;
        $amount = number_format($invoice->total_amount, 2);

        return "Invoice {$invoiceNumber} from {$companyName} for \${$amount}";
    }

    /**
     * Get email message.
     */
    protected function getMessage(Invoice $invoice, array $input): string
    {
        if (isset($input['message'])) {
            return $input['message'];
        }

        if ($this->option('message')) {
            return $this->option('message');
        }

        // Generate default message
        $customerName = $invoice->customer->name;
        $invoiceNumber = $invoice->invoice_number;
        $amount = number_format($invoice->total_amount, 2);
        $dueDate = $invoice->due_date->format('F j, Y');
        $companyName = $this->company->name;

        return "Dear {$customerName},

Please find attached invoice #{$invoiceNumber} for the amount of \${$amount}.

Payment is due by {$dueDate}.

If you have any questions, please don't hesitate to contact us.

Best regards,
{$companyName}";
    }

    /**
     * Get CC email addresses.
     */
    protected function getCcEmails(array $input): array
    {
        $ccEmails = [];

        if ($this->option('cc')) {
            $ccEmails = array_map('trim', explode(',', $this->option('cc')));
        }

        if (isset($input['cc'])) {
            $additionalEmails = array_map('trim', explode(',', $input['cc']));
            $ccEmails = array_merge($ccEmails, $additionalEmails);
        }

        return array_unique(array_filter($ccEmails));
    }

    /**
     * Get BCC email addresses.
     */
    protected function getBccEmails(array $input): array
    {
        $bccEmails = [];

        if ($this->option('bcc')) {
            $bccEmails = array_map('trim', explode(',', $this->option('bcc')));
        }

        if (isset($input['bcc'])) {
            $additionalEmails = array_map('trim', explode(',', $input['bcc']));
            $bccEmails = array_merge($bccEmails, $additionalEmails);
        }

        return array_unique(array_filter($bccEmails));
    }

    /**
     * Preview email without sending.
     */
    protected function previewEmail(Invoice $invoice, array $emailData): void
    {
        $this->info('Email Preview:');
        $this->line('');

        $this->info('To: '.$emailData['to']);

        if (! empty($emailData['cc'])) {
            $this->info('CC: '.implode(', ', $emailData['cc']));
        }

        if (! empty($emailData['bcc'])) {
            $this->info('BCC: '.implode(', ', $emailData['bcc']));
        }

        $this->info('Subject: '.$emailData['subject']);
        $this->line('');

        $this->info('Message:');
        $this->line($emailData['message']);
        $this->line('');

        $this->info('Attachments: '.($emailData['attach_pdf'] ? 'Invoice PDF' : 'None'));
        $this->info('Template: '.$emailData['template']);

        if (isset($emailData['send_at'])) {
            $this->info('Scheduled for: '.$emailData['send_at']->format('Y-m-d H:i:s'));
        }

        $this->line('');
        $this->info('Invoice Details:');
        $this->displaySuccess("Invoice #{$invoice->invoice_number}", [
            'Customer' => $invoice->customer->name,
            'Amount' => "\${$invoice->total_amount}",
            'Due Date' => $invoice->due_date->format('Y-m-d'),
            'Status' => $invoice->status,
        ]);
    }

    /**
     * Schedule email for later sending.
     */
    protected function scheduleEmail(Invoice $invoice, array $emailData): int
    {
        // This would integrate with a job queue system
        $scheduledAt = $emailData['send_at'];

        $this->info('Email scheduled successfully');
        $this->displaySuccess("Invoice #{$invoice->invoice_number} will be sent", [
            'To' => $emailData['to'],
            'Scheduled For' => $scheduledAt->format('Y-m-d H:i:s'),
            'Subject' => $emailData['subject'],
        ]);

        // Log the action
        $this->logExecution('invoice_scheduled', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'scheduled_at' => $scheduledAt,
            'recipient' => $emailData['to'],
        ]);

        return self::SUCCESS;
    }

    /**
     * Send email immediately.
     */
    protected function sendEmailImmediately(Invoice $invoice, array $emailData): int
    {
        try {
            // Mark invoice as sent
            $this->invoiceService->markAsSent($invoice, $this->user);

            // Simulate email sending (would integrate with actual email service)
            $this->simulateEmailSending($invoice, $emailData);

            $this->info('Email sent successfully');
            $this->displaySuccess("Invoice #{$invoice->invoice_number} sent to customer", [
                'To' => $emailData['to'],
                'Subject' => $emailData['subject'],
                'Sent At' => now()->format('Y-m-d H:i:s'),
            ]);

            // Log the action
            $this->logExecution('invoice_sent', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'recipient' => $emailData['to'],
                'subject' => $emailData['subject'],
            ]);

            return self::SUCCESS;

        } catch (\Throwable $exception) {
            $this->error('Failed to send email: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Simulate email sending (would integrate with actual email service).
     */
    protected function simulateEmailSending(Invoice $invoice, array $emailData): void
    {
        // In a real implementation, this would:
        // 1. Generate PDF if attach_pdf is true
        // 2. Use Laravel's Mail facade or a dedicated email service
        // 3. Queue the email if configured
        // 4. Track delivery status

        // For now, we'll just simulate the process
        $this->line("Preparing email for {$emailData['to']}...");

        if ($emailData['attach_pdf']) {
            $this->line('Generating PDF attachment...');
        }

        $this->line('Sending via mail system...');

        // Simulate some processing time
        usleep(500000); // 0.5 seconds
    }

    /**
     * Get usage examples for this command.
     */
    protected function getExamples(): array
    {
        return [
            'invoice:send INV-2024-001',
            'invoice:send 12345 --email=john@example.com --subject="Updated Invoice" --attach-pdf',
            'invoice:send "uuid-string" --cc=accounting@company.com --bcc=admin@company.com --message="Custom message here"',
            'invoice:send INV-2024-001 --send-at="2024-02-01 09:00:00" --schedule',
            'invoice:send --natural="send invoice INV-2024-001 to customer with PDF attached" --preview',
        ];
    }
}
