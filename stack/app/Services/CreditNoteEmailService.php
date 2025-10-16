<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CreditNote;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CreditNoteEmailService
{
    public function __construct(
        private readonly ContextService $contextService,
        private readonly AuthService $authService
    ) {}

    /**
     * Send credit note via email to customer.
     */
    public function sendCreditNoteEmail(CreditNote $creditNote, User $user, array $options = []): array
    {
        $this->authService->canAccessCompany($user, $creditNote->company);
        $this->authService->hasPermission($user, 'credit_notes.email');

        // Generate PDF if not provided
        $pdfPath = $options['pdf_path'] ?? null;
        if (! $pdfPath) {
            $pdfPath = $this->generatePdfForEmail($creditNote, $user);
        }

        // Prepare email data
        $emailData = $this->prepareEmailData($creditNote, $pdfPath, $options);

        // Send email
        $result = $this->sendEmail($emailData);

        // Log the email send
        activity()
            ->performedOn($creditNote)
            ->causedBy($user)
            ->withProperties([
                'action' => 'credit_note_email_sent',
                'recipient' => $creditNote->invoice->customer->email,
                'subject' => $emailData['subject'],
                'pdf_path' => $pdfPath,
                'result' => $result,
            ])
            ->log('Credit note sent via email');

        return $result;
    }

    /**
     * Send multiple credit notes via email.
     */
    public function sendBatchCreditNoteEmails(array $creditNotes, User $user, array $options = []): array
    {
        if (empty($creditNotes)) {
            throw new \InvalidArgumentException('No credit notes provided for batch email');
        }

        // Verify all credit notes belong to the same company
        $company = $creditNotes[0]->company;
        foreach ($creditNotes as $creditNote) {
            if ($creditNote->company_id !== $company->id) {
                throw new \InvalidArgumentException('All credit notes must belong to the same company');
            }
        }

        $this->authService->canAccessCompany($user, $company);
        $this->authService->hasPermission($user, 'credit_notes.email');

        $results = [];

        foreach ($creditNotes as $creditNote) {
            try {
                $result = $this->sendCreditNoteEmail($creditNote, $user, $options);
                $results[] = [
                    'credit_note_id' => $creditNote->id,
                    'credit_note_number' => $creditNote->credit_note_number,
                    'success' => true,
                    'result' => $result,
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'credit_note_id' => $creditNote->id,
                    'credit_note_number' => $creditNote->credit_note_number,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Schedule credit note email to be sent later.
     */
    public function scheduleCreditNoteEmail(CreditNote $creditNote, User $user, \DateTimeInterface $sendAt, array $options = []): bool
    {
        $this->authService->canAccessCompany($user, $creditNote->company);
        $this->authService->hasPermission($user, 'credit_notes.email');

        // Store scheduled email
        $scheduledEmail = [
            'id' => Str::uuid(),
            'company_id' => $creditNote->company_id,
            'credit_note_id' => $creditNote->id,
            'user_id' => $user->id,
            'recipient_email' => $creditNote->invoice->customer->email,
            'send_at' => $sendAt,
            'status' => 'scheduled',
            'options' => $options,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        \DB::table('acct.scheduled_credit_note_emails')->insert($scheduledEmail);

        // Log the scheduling
        activity()
            ->performedOn($creditNote)
            ->causedBy($user)
            ->withProperties([
                'action' => 'credit_note_email_scheduled',
                'recipient' => $creditNote->invoice->customer->email,
                'send_at' => $sendAt->format('Y-m-d H:i:s'),
            ])
            ->log('Credit note email scheduled');

        return true;
    }

    /**
     * Send email reminder for unpaid credit notes.
     */
    public function sendUnpaidCreditNoteReminder(Company $company, User $user, array $options = []): array
    {
        $this->authService->canAccessCompany($user, $company);
        $this->authService->hasPermission($user, 'credit_notes.email');

        // Find unpaid credit notes that need reminders
        $creditNotes = CreditNote::forCompany($company->id)
            ->where('status', 'posted')
            ->whereHas('invoice', function ($query) use ($options) {
                $query->where('balance_due', '>', 0);
                if (isset($options['customer_id'])) {
                    $query->where('customer_id', $options['customer_id']);
                }
            })
            ->with(['invoice.customer'])
            ->get();

        $results = [];
        $sentCount = 0;
        $failedCount = 0;

        foreach ($creditNotes as $creditNote) {
            try {
                $result = $this->sendCreditNoteReminder($creditNote, $user, $options);
                $results[] = [
                    'credit_note_id' => $creditNote->id,
                    'credit_note_number' => $creditNote->credit_note_number,
                    'success' => $result['success'],
                    'message' => $result['message'] ?? null,
                ];

                if ($result['success']) {
                    $sentCount++;
                } else {
                    $failedCount++;
                }
            } catch (\Throwable $e) {
                $failedCount++;
                $results[] = [
                    'credit_note_id' => $creditNote->id,
                    'credit_note_number' => $creditNote->credit_note_number,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Log summary
        activity()
            ->performedOn($company)
            ->causedBy($user)
            ->withProperties([
                'action' => 'unpaid_credit_notes_reminder_sent',
                'total_credit_notes' => $creditNotes->count(),
                'sent_count' => $sentCount,
                'failed_count' => $failedCount,
            ])
            ->log('Unpaid credit notes reminder sent');

        return $results;
    }

    /**
     * Generate PDF for email.
     */
    private function generatePdfForEmail(CreditNote $creditNote, User $user): string
    {
        $pdfService = app(CreditNotePdfService::class);

        $pdfOptions = [
            'disk' => 'local',
            'directory' => 'temp/emails',
            'filename_template' => 'credit-note-{number}',
            'color_scheme' => 'email',
        ];

        return $pdfService->generateCreditNotePdf($creditNote, $user, $pdfOptions);
    }

    /**
     * Prepare email data.
     */
    private function prepareEmailData(CreditNote $creditNote, string $pdfPath, array $options): array
    {
        $company = $creditNote->company;
        $customer = $creditNote->invoice->customer;

        return [
            'to' => $options['to'] ?? $customer->email,
            'cc' => $options['cc'] ?? $this->getCcEmails($creditNote, $company),
            'bcc' => $options['bcc'] ?? $this->getBccEmails($company),
            'subject' => $this->generateSubject($creditNote, $options),
            'view' => $options['view'] ?? 'emails.credit-note',
            'data' => [
                'credit_note' => $creditNote,
                'company' => $company,
                'customer' => $customer,
                'invoice' => $creditNote->invoice,
                'pdf_url' => $this->getPdfUrl($pdfPath),
                'pdf_path' => $pdfPath,
                'message' => $options['message'] ?? $this->getDefaultEmailMessage($creditNote),
                'signature' => $options['signature'] ?? $this->getEmailSignature($company),
                'settings' => $this->getEmailSettings($company),
            ],
            'attachments' => [
                [
                    'path' => storage_path($pdfPath),
                    'as' => $this->generateAttachmentName($creditNote),
                    'mime' => 'application/pdf',
                ],
            ],
        ];
    }

    /**
     * Send email.
     */
    private function sendEmail(array $emailData): array
    {
        try {
            Mail::send($emailData['view'], $emailData['data'], function ($message) use ($emailData) {
                $message->to($emailData['to'])
                    ->cc($emailData['cc'] ?? [])
                    ->bcc($emailData['bcc'] ?? [])
                    ->subject($emailData['subject']);

                foreach ($emailData['attachments'] as $attachment) {
                    $message->attach($attachment['path'], [
                        'as' => $attachment['as'],
                        'mime' => $attachment['mime'],
                    ]);
                }
            });

            return [
                'success' => true,
                'message' => 'Email sent successfully',
                'timestamp' => now()->format('Y-m-d H:i:s'),
            ];

        } catch (\Throwable $e) {
            \Log::error('Failed to send credit note email', [
                'error' => $email->getMessage(),
                'trace' => $email->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => now()->format('Y-m-d H:i:s'),
            ];
        }
    }

    /**
     * Generate email subject.
     */
    private function generateSubject(CreditNote $creditNote, array $options): string
    {
        $template = $options['subject_template'] ?? 'Credit Note {number}';

        $replacements = [
            '{number}' => $creditNote->credit_note_number,
            '{company}' => $creditNote->company->name,
            '{customer}' => $creditNote->invoice->customer->name,
            '{amount}' => $this->formatCurrency($creditNote->total_amount, $creditNote->currency),
            '{date}' => $creditNote->created_at->format('F j, Y'),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * Get CC emails for credit note.
     */
    private function getCcEmails(CreditNote $creditNote, Company $company): array
    {
        $emails = [];

        // Company default CC emails
        if ($company->email_settings['default_cc'] ?? null) {
            $emails = array_merge($emails, $company->email_settings['default_cc']);
        }

        // Credit note specific CC
        if ($creditNote->invoice->customer->cc_emails) {
            $emails = array_merge($emails, $creditNote->invoice->customer->cc_emails);
        }

        return array_unique($emails);
    }

    /**
     * Get BCC emails for company.
     */
    private function getBccEmails(Company $company): array
    {
        return $company->email_settings['default_bcc'] ?? [];
    }

    /**
     * Get default email message.
     */
    private function getDefaultEmailMessage(CreditNote $creditNote): string
    {
        return "Dear {$creditNote->invoice->customer->name},

We have issued a credit note for your recent invoice.

Credit Note Details:
- Number: {$creditNote->credit_note_number}
- Original Invoice: {$creditNote->invoice->invoice_number}
- Amount: {$this->formatCurrency($creditNote->total_amount, $creditNote->currency)}
- Reason: {$creditNote->reason}

Please find the detailed credit note attached to this email.

If you have any questions, please don't hesitate to contact us.

Best regards,
{$creditNote->company->name}";
    }

    /**
     * Get email signature.
     */
    private function getEmailSignature(Company $company): string
    {
        $signature = $company->email_settings['signature'] ?? '';

        if (! $signature) {
            $signature = "Best regards,\n{$company->name}\n";
            if ($company->phone) {
                $signature .= "Phone: {$company->phone}\n";
            }
            if ($company->website) {
                $signature .= "Website: {$company->website}\n";
            }
        }

        return $signature;
    }

    /**
     * Get email settings for company.
     */
    private function getEmailSettings(Company $company): array
    {
        return [
            'sender_name' => $company->email_settings['sender_name'] ?? $company->name,
            'sender_email' => $company->email_settings['sender_email'] ?? $company->email,
            'reply_to' => $company->email_settings['reply_to'] ?? null,
            'logo_url' => $this->getCompanyLogoUrl($company),
            'brand_color' => $company->email_settings['brand_color'] ?? '#007bff',
            'footer_text' => $company->email_settings['footer_text'] ?? null,
        ];
    }

    /**
     * Get company logo URL for email.
     */
    private function getCompanyLogoUrl(Company $company): ?string
    {
        if (! $company->logo_path) {
            return null;
        }

        return Storage::disk('public')->url($company->logo_path);
    }

    /**
     * Get PDF URL for email.
     */
    private function getPdfUrl(string $pdfPath): string
    {
        // For local storage, we would typically serve via a route
        // For now, return a placeholder URL
        return url('/download/pdf/'.basename($pdfPath));
    }

    /**
     * Generate attachment name.
     */
    private function generateAttachmentName(CreditNote $creditNote): string
    {
        return "Credit-Note-{$creditNote->credit_note_number}.pdf";
    }

    /**
     * Format currency for display.
     */
    private function formatCurrency(float $amount, string $currency): string
    {
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'CAD' => '$',
            'AUD' => '$',
            'JPY' => '¥',
            'CNY' => '¥',
            'INR' => '₹',
        ];

        $symbol = $symbols[$currency] ?? $currency;

        return $symbol.number_format($amount, 2);
    }

    /**
     * Send reminder email for unpaid credit note.
     */
    private function sendCreditNoteReminder(CreditNote $creditNote, User $user, array $options): array
    {
        $emailData = [
            'to' => $creditNote->invoice->customer->email,
            'subject' => $this->generateReminderSubject($creditNote),
            'view' => 'emails.credit-note-reminder',
            'data' => [
                'credit_note' => $creditNote,
                'company' => $creditNote->company,
                'customer' => $creditNote->invoice->customer,
                'message' => $options['message'] ?? $this->getReminderMessage($creditNote),
                'settings' => $this->getEmailSettings($creditNote->company),
            ],
        ];

        return $this->sendEmail($emailData);
    }

    /**
     * Generate reminder subject.
     */
    private function generateReminderSubject(CreditNote $creditNote): string
    {
        return "Reminder: Credit Note {$creditNote->credit_note_number} Available";
    }

    /**
     * Get reminder message.
     */
    private function getReminderMessage(CreditNote $creditNote): string
    {
        return "Dear {$creditNote->invoice->customer->name},

This is a reminder that you have an available credit note that can be applied to your invoice balance.

Credit Note Details:
- Number: {$creditNote->credit_note_number}
- Available Amount: {$this->formatCurrency($creditNote->remaining_balance, $creditNote->currency)}
- Original Invoice: {$creditNote->invoice->invoice_number}
- Reason: {$creditNote->reason}

This credit note can be automatically applied to your next payment or manually applied upon request.

Please contact us if you have any questions about applying this credit note.

Best regards,
{$creditNote->company->name}";
    }

    /**
     * Process scheduled emails.
     */
    public function processScheduledEmails(): array
    {
        $scheduledEmails = \DB::table('acct.scheduled_credit_note_emails')
            ->where('status', 'scheduled')
            ->where('send_at', '<=', now())
            ->orderBy('send_at', 'asc')
            ->limit(50)
            ->get();

        $processed = 0;
        $failed = 0;

        foreach ($scheduledEmails as $scheduledEmail) {
            try {
                $creditNote = CreditNote::find($scheduledEmail->credit_note_id);
                $user = User::find($scheduledEmail->user_id);

                if ($creditNote && $user) {
                    $result = $this->sendCreditNoteEmail($creditNote, $user, $scheduledEmail->options);

                    \DB::table('acct.scheduled_credit_note_emails')
                        ->where('id', $scheduledEmail->id)
                        ->update([
                            'status' => $result['success'] ? 'sent' : 'failed',
                            'sent_at' => now(),
                            'result' => json_encode($result),
                            'updated_at' => now(),
                        ]);

                    if ($result['success']) {
                        $processed++;
                    } else {
                        $failed++;
                    }
                } else {
                    // Mark as failed if credit note or user not found
                    \DB::table('acct.scheduled_credit_note_emails')
                        ->where('id', $scheduledEmail->id)
                        ->update([
                            'status' => 'failed',
                            'error' => 'Credit note or user not found',
                            'updated_at' => now(),
                        ]);
                    $failed++;
                }
            } catch (\Throwable $e) {
                \DB::table('acct.scheduled_credit_note_emails')
                    ->where('id', $scheduledEmail->id)
                    ->update([
                        'status' => 'failed',
                        'error' => $e->getMessage(),
                        'updated_at' => now(),
                    ]);
                $failed++;
            }
        }

        return [
            'total_processed' => $scheduledEmails->count(),
            'successful' => $processed,
            'failed' => $failed,
        ];
    }
}
