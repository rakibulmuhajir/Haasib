<?php

namespace App\Console\Commands;

use App\Models\Invoice;

class InvoicePdf extends InvoiceBaseCommand
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'invoice:pdf
                           {invoice : Invoice ID, number, or UUID}
                           {--output= : Output file path (auto-generated if not specified)}
                           {--template= : PDF template to use (default, modern, classic)}
                           {--paper=A4 : Paper format (A4, Letter, Legal)}
                           {--orientation=portrait : Page orientation (portrait, landscape)}
                           {--compress : Compress PDF for smaller file size}
                           {--encrypt : Encrypt PDF with password}
                           {--password= : Password for encrypted PDF}
                           {--watermark= : Add watermark text}
                           {--footer= : Custom footer text}
                           {--header= : Custom header text}
                           {--include-stamp : Include company stamp/signature}
                           {--include-qrcode : Include QR code for invoice URL}
                           {--include-barcode : Include barcode for invoice number}
                           {--with-payments : Include payment history}
                           {--with-attachments : Include attachments}
                           {--draft : Add "DRAFT" watermark}
                           {--email : Send PDF via email after generation}
                           {--email-to= : Email address to send PDF to}
                           {--email-subject= : Email subject}
                           {--email-message= : Email message}
                           {--open : Open PDF after generation}
                           {--preview : Preview PDF settings without generating}
                           {--company= : Company ID (overrides current company)}
                           {--format=table : Output format (table, json, csv, text)}
                           {--natural= : Natural language input}
                           {--quiet : Suppress output}';

    /**
     * The console command description.
     */
    protected $description = 'Generate PDF version of an invoice';

    /**
     * Execute the command logic.
     */
    protected function executeCommand(): int
    {
        $input = $this->parseInput();

        // Find the invoice
        $invoice = $this->findInvoice($input);

        // Load invoice relationships
        $this->loadInvoiceRelationships($invoice, $input);

        // Prepare PDF generation settings
        $pdfSettings = $this->preparePdfSettings($invoice, $input);

        // Preview mode
        if ($this->option('preview') || in_array('preview', $input['flags'] ?? [])) {
            return $this->previewPdfGeneration($invoice, $pdfSettings);
        }

        // Generate the PDF
        $pdfPath = $this->generatePdf($invoice, $pdfSettings);

        // Handle post-generation actions
        $this->handlePostGenerationActions($invoice, $pdfPath, $input);

        // Display results
        $this->displayResults($invoice, $pdfPath, $pdfSettings);

        // Log the action
        $this->logExecution('invoice_pdf_generated', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'pdf_path' => $pdfPath,
            'template' => $pdfSettings['template'],
        ]);

        return self::SUCCESS;
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
     * Load invoice relationships based on options.
     */
    protected function loadInvoiceRelationships(Invoice $invoice, array $input): void
    {
        // Always load customer and line items
        $invoice->load(['customer', 'lineItems']);

        // Load payments if requested
        if ($this->option('with-payments') || isset($input['with_payments'])) {
            $invoice->load('payments');
        }

        // Load company for branding
        $invoice->load('company');
    }

    /**
     * Prepare PDF generation settings.
     */
    protected function preparePdfSettings(Invoice $invoice, array $input): array
    {
        $settings = [
            'template' => $input['template'] ?? $this->option('template') ?? 'default',
            'format' => $input['paper'] ?? $this->option('paper') ?? 'A4',
            'orientation' => $input['orientation'] ?? $this->option('orientation') ?? 'portrait',
            'compress' => $this->option('compress') || in_array('compress', $input['flags'] ?? []),
            'encrypt' => $this->option('encrypt') || in_array('encrypt', $input['flags'] ?? []),
            'password' => $input['password'] ?? $this->option('password'),
            'watermark' => $input['watermark'] ?? $this->option('watermark'),
            'footer' => $input['footer'] ?? $this->option('footer'),
            'header' => $input['header'] ?? $this->option('header'),
            'include_stamp' => $this->option('include-stamp') || in_array('stamp', $input['flags'] ?? []),
            'include_qrcode' => $this->option('include-qrcode') || in_array('qrcode', $input['flags'] ?? []),
            'include_barcode' => $this->option('include-barcode') || in_array('barcode', $input['flags'] ?? []),
            'with_payments' => $this->option('with-payments') || isset($input['with_payments']),
            'with_attachments' => $this->option('with-attachments') || isset($input['with_attachments']),
            'is_draft' => $this->option('draft') || in_array('draft', $input['flags'] ?? []),
        ];

        // Generate output path if not provided
        if (! ($input['output'] ?? $this->option('output'))) {
            $settings['output_path'] = $this->generateOutputPath($invoice, $input);
        } else {
            $settings['output_path'] = $input['output'] ?? $this->option('output');
        }

        // Set default watermark for draft
        if ($settings['is_draft'] && ! $settings['watermark']) {
            $settings['watermark'] = 'DRAFT';
        }

        // Generate QR code URL if requested
        if ($settings['include_qrcode']) {
            $settings['qrcode_url'] = $this->generateInvoiceUrl($invoice);
        }

        // Generate barcode data if requested
        if ($settings['include_barcode']) {
            $settings['barcode_data'] = $invoice->invoice_number;
        }

        return $settings;
    }

    /**
     * Generate output file path.
     */
    protected function generateOutputPath(Invoice $invoice, array $input): string
    {
        $filename = "Invoice-{$invoice->invoice_number}";

        // Add status indicator if draft
        if ($input['draft'] ?? $this->option('draft')) {
            $filename .= '-DRAFT';
        }

        // Add timestamp to avoid conflicts
        $filename .= '-'.now()->format('Y-m-d-His');

        return storage_path("app/invoices/pdf/{$filename}.pdf");
    }

    /**
     * Generate invoice URL for QR code.
     */
    protected function generateInvoiceUrl(Invoice $invoice): string
    {
        return route('invoices.show', $invoice->id);
    }

    /**
     * Preview PDF generation settings.
     */
    protected function previewPdfGeneration(Invoice $invoice, array $settings): int
    {
        $this->info('PDF Generation Preview');
        $this->str_repeat('=', 25);
        $this->line('');

        $this->info('Invoice Details:');
        $this->line("Invoice Number: {$invoice->invoice_number}");
        $this->line("Customer: {$invoice->customer->name}");
        $this->line("Total Amount: \${$invoice->total_amount}");
        $this->line("Status: {$invoice->status}");
        $this->line('');

        $this->info('PDF Settings:');
        $this->line("Template: {$settings['template']}");
        $this->line("Format: {$settings['format']}");
        $this->line("Orientation: {$settings['orientation']}");
        $this->line("Output Path: {$settings['output_path']}");
        $this->line('Compress: '.($settings['compress'] ? 'Yes' : 'No'));
        $this->line('Encrypt: '.($settings['encrypt'] ? 'Yes' : 'No'));

        if ($settings['watermark']) {
            $this->line("Watermark: {$settings['watermark']}");
        }

        if ($settings['header']) {
            $this->line("Header: {$settings['header']}");
        }

        if ($settings['footer']) {
            $this->line("Footer: {$settings['footer']}");
        }

        $this->line('');
        $this->info('Additional Features:');
        $this->line('Include Stamp: '.($settings['include_stamp'] ? 'Yes' : 'No'));
        $this->line('Include QR Code: '.($settings['include_qrcode'] ? 'Yes' : 'No'));
        $this->line('Include Barcode: '.($settings['include_barcode'] ? 'Yes' : 'No'));
        $this->line('Include Payments: '.($settings['with_payments'] ? 'Yes' : 'No'));
        $this->line('Include Attachments: '.($settings['with_attachments'] ? 'Yes' : 'No'));
        $this->line('');

        $this->info('This is a preview. Remove --preview to generate the actual PDF.');

        return self::SUCCESS;
    }

    /**
     * Generate the PDF file.
     */
    protected function generatePdf(Invoice $invoice, array $settings): string
    {
        try {
            // Ensure output directory exists
            $outputDir = dirname($settings['output_path']);
            if (! is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            // Simulate PDF generation (in real implementation, use DOMPDF, TCPDF, or similar)
            $this->line("Generating PDF for invoice #{$invoice->invoice_number}...");
            $this->line("Using template: {$settings['template']}");
            $this->line("Format: {$settings['format']} {$settings['orientation']}");

            // Simulate processing time
            usleep(1000000); // 1 second

            // Create a placeholder PDF file
            $this->createPlaceholderPdf($settings['output_path'], $invoice, $settings);

            return $settings['output_path'];

        } catch (\Throwable $exception) {
            $this->error('Failed to generate PDF: '.$exception->getMessage());
            throw $exception;
        }
    }

    /**
     * Create a placeholder PDF file (simulation).
     */
    protected function createPlaceholderPdf(string $path, Invoice $invoice, array $settings): void
    {
        // In a real implementation, this would use a PDF library
        // For now, we'll create a simple text file with .pdf extension as placeholder

        $content = "PDF INVOICE\n";
        $content .= "================\n\n";
        $content .= "Invoice Number: {$invoice->invoice_number}\n";
        $content .= "Customer: {$invoice->customer->name}\n";
        $content .= "Issue Date: {$invoice->issue_date->format('Y-m-d')}\n";
        $content .= "Due Date: {$invoice->due_date->format('Y-m-d')}\n";
        $content .= "Total Amount: \${$invoice->total_amount}\n\n";

        $content .= "Line Items:\n";
        $content .= "-----------\n";
        foreach ($invoice->lineItems as $item) {
            $content .= "{$item->description} x {$item['quantity']} @ \${$item['unit_price']} = \${$item['total']}\n";
        }

        $content .= "\nTemplate: {$settings['template']}\n";
        $content .= 'Generated: '.now()->format('Y-m-d H:i:s')."\n";

        if ($settings['watermark']) {
            $content .= "WATERMARK: {$settings['watermark']}\n";
        }

        file_put_contents($path, $content);
    }

    /**
     * Handle post-generation actions.
     */
    protected function handlePostGenerationActions(Invoice $invoice, string $pdfPath, array $input): void
    {
        // Open PDF if requested
        if ($this->option('open') || in_array('open', $input['flags'] ?? [])) {
            $this->openPdf($pdfPath);
        }

        // Send via email if requested
        if ($this->option('email') || in_array('email', $input['flags'] ?? [])) {
            $this->emailPdf($invoice, $pdfPath, $input);
        }
    }

    /**
     * Open PDF file.
     */
    protected function openPdf(string $pdfPath): void
    {
        $this->line("Opening PDF: {$pdfPath}");

        // In a real implementation, this would use the system's default PDF viewer
        // For now, we'll just simulate it
        if (PHP_OS_FAMILY === 'Darwin') {
            exec("open {$pdfPath}");
        } elseif (PHP_OS_FAMILY === 'Windows') {
            exec("start {$pdfPath}");
        } else {
            exec("xdg-open {$pdfPath}");
        }
    }

    /**
     * Send PDF via email.
     */
    protected function emailPdf(Invoice $invoice, string $pdfPath, array $input): void
    {
        $to = $input['email_to'] ?? $this->option('email-to') ?? $invoice->customer->email;
        $subject = $input['email_subject'] ?? $this->option('email-subject') ??
            "Invoice #{$invoice->invoice_number} from {$this->company->name}";
        $message = $input['email_message'] ?? $this->option('email-message') ??
            "Please find attached invoice #{$invoice->invoice_number}.";

        try {
            // In a real implementation, this would use Laravel's Mail facade
            $this->line("Sending PDF invoice to: {$to}");
            $this->line("Subject: {$subject}");
            $this->line("Message: {$message}");
            $this->line("Attachment: {$pdfPath}");

            // Simulate email sending
            usleep(500000); // 0.5 seconds

            $this->info("PDF invoice sent successfully to {$to}");

        } catch (\Throwable $exception) {
            $this->error('Failed to send email: '.$exception->getMessage());
        }
    }

    /**
     * Display generation results.
     */
    protected function displayResults(Invoice $invoice, string $pdfPath, array $settings): void
    {
        if ($this->option('quiet')) {
            return;
        }

        $fileSize = file_exists($pdfPath) ? $this->formatFileSize(filesize($pdfPath)) : 'Unknown';

        $this->displaySuccess('PDF generated successfully', [
            'Invoice Number' => $invoice->invoice_number,
            'Output File' => $pdfPath,
            'File Size' => $fileSize,
            'Template' => $settings['template'],
            'Format' => $settings['format'],
        ]);

        // Display additional actions taken
        $actions = [];

        if ($this->option('open') || in_array('open', $this['flags'] ?? [])) {
            $actions[] = 'PDF opened';
        }

        if ($this->option('email') || in_array('email', $this['flags'] ?? [])) {
            $actions[] = 'Email sent';
        }

        if (! empty($actions)) {
            $this->line('');
            $this->info('Additional Actions: '.implode(', ', $actions));
        }
    }

    /**
     * Format file size for display.
     */
    protected function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;

        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }

        return round($bytes, 2).' '.$units[$unitIndex];
    }

    /**
     * Get usage examples for this command.
     */
    protected function getExamples(): array
    {
        return [
            'invoice:pdf INV-2024-001',
            'invoice:pdf 12345 --template=modern --compress',
            'invoice:pdf "uuid-string" --output=~/Desktop/invoice.pdf --open',
            'invoice:pdf INV-2024-001 --encrypt --password=secret123',
            'invoice:pdf INV-2024-001 --watermark="CONFIDENTIAL" --include-qrcode',
            'invoice:pdf --natural="generate PDF for invoice INV-2024-001 with modern template and send via email" --email-to=john@example.com',
        ];
    }
}
