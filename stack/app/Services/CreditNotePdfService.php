<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CreditNote;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CreditNotePdfService
{
    public function __construct(
        private readonly ContextService $contextService,
        private readonly AuthService $authService
    ) {}

    /**
     * Generate PDF for a credit note.
     */
    public function generateCreditNotePdf(CreditNote $creditNote, User $user, array $options = []): string
    {
        $this->authService->canAccessCompany($user, $creditNote->company);
        $this->authService->hasPermission($user, 'credit_notes.pdf');

        // Prepare data for PDF template
        $data = $this->preparePdfData($creditNote, $options);

        // Generate PDF
        $pdf = Pdf::loadView('pdf.credit-note', $data)
            ->setPaper($options['paper_size'] ?? 'a4')
            ->setOrientation($options['orientation'] ?? 'portrait')
            ->setOption('defaultFont', $options['font'] ?? 'sans-serif');

        // Generate filename
        $filename = $this->generateFilename($creditNote, $options);

        // Save to storage
        $path = $this->savePdf($pdf, $filename, $creditNote->company, $options);

        // Log the PDF generation
        activity()
            ->performedOn($creditNote)
            ->causedBy($user)
            ->withProperties([
                'action' => 'credit_note_pdf_generated',
                'filename' => $filename,
                'path' => $path,
                'size' => Storage::size($path),
            ])
            ->log('Credit note PDF generated');

        return $path;
    }

    /**
     * Prepare data for PDF template.
     */
    private function preparePdfData(CreditNote $creditNote, array $options): array
    {
        $company = $creditNote->company;
        $invoice = $creditNote->invoice;
        $customer = $invoice->customer;

        return [
            'credit_note' => $creditNote,
            'invoice' => $invoice,
            'customer' => $customer,
            'company' => $company,
            'items' => $creditNote->items,
            'logo_url' => $this->getCompanyLogoUrl($company),
            'generated_at' => now()->format('Y-m-d H:i:s'),
            'generated_by' => $options['generated_by'] ?? auth()->user()->name,
            'settings' => $this->getPdfSettings($company),
            'currency_symbol' => $this->getCurrencySymbol($creditNote->currency),
            'tax_details' => $this->getTaxDetails($creditNote),
            'totals' => $this->calculateTotals($creditNote),
            'notes' => $this->formatNotes($creditNote->notes),
            'terms' => $this->formatTerms($creditNote->terms),
        ];
    }

    /**
     * Generate filename for PDF.
     */
    private function generateFilename(CreditNote $creditNote, array $options): string
    {
        $template = $options['filename_template'] ?? 'credit-note-{number}-{date}';

        $replacements = [
            '{number}' => $creditNote->credit_note_number,
            '{date}' => $creditNote->created_at->format('Y-m-d'),
            '{customer}' => Str::slug($creditNote->invoice->customer->name),
            '{company}' => Str::slug($creditNote->company->name),
        ];

        $filename = str_replace(array_keys($replacements), array_values($replacements), $template);

        return $filename.'.pdf';
    }

    /**
     * Save PDF to storage.
     */
    private function savePdf($pdf, string $filename, Company $company, array $options): string
    {
        $disk = $options['disk'] ?? 'public';
        $directory = $options['directory'] ?? "companies/{$company->id}/credit-notes";

        // Ensure directory exists
        $fullDirectory = $directory;
        if ($disk === 'local') {
            $fullDirectory = storage_path('app/'.$directory);
            if (! is_dir($fullDirectory)) {
                mkdir($fullDirectory, 0755, true);
            }
        }

        $path = "{$directory}/{$filename}";

        // Save to storage
        Storage::disk($disk)->put($path, $pdf->output());

        return $path;
    }

    /**
     * Get company logo URL.
     */
    private function getCompanyLogoUrl(Company $company): ?string
    {
        if (! $company->logo_path) {
            return null;
        }

        return Storage::disk('public')->url($company->logo_path);
    }

    /**
     * Get PDF settings for company.
     */
    private function getPdfSettings(Company $company): array
    {
        return [
            'show_company_logo' => $company->pdf_settings['show_logo'] ?? true,
            'show_watermark' => $company->pdf_settings['show_watermark'] ?? false,
            'watermark_text' => $company->pdf_settings['watermark_text'] ?? null,
            'show_amount_in_words' => $company->pdf_settings['show_amount_in_words'] ?? true,
            'show_barcode' => $company->pdf_settings['show_barcode'] ?? true,
            'show_qr_code' => $company->pdf_settings['show_qr_code'] ?? false,
            'color_scheme' => $company->pdf_settings['color_scheme'] ?? 'default',
            'footer_text' => $company->pdf_settings['footer_text'] ?? null,
        ];
    }

    /**
     * Get currency symbol.
     */
    private function getCurrencySymbol(string $currency): string
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

        return $symbols[$currency] ?? $currency;
    }

    /**
     * Get tax details.
     */
    private function getTaxDetails(CreditNote $creditNote): array
    {
        $taxDetails = [];

        foreach ($creditNote->items as $item) {
            if ($item->tax_rate > 0) {
                $taxRate = $item->tax_rate;
                if (! isset($taxDetails[$taxRate])) {
                    $taxDetails[$taxRate] = [
                        'rate' => $taxRate,
                        'taxable_amount' => 0,
                        'tax_amount' => 0,
                    ];
                }

                $taxableAmount = $item->subtotal;
                $taxAmount = $item->tax_amount;

                $taxDetails[$taxRate]['taxable_amount'] += $taxableAmount;
                $taxDetails[$taxRate]['tax_amount'] += $taxAmount;
            }
        }

        return array_values($taxDetails);
    }

    /**
     * Calculate totals.
     */
    private function calculateTotals(CreditNote $creditNote): array
    {
        return [
            'subtotal' => $creditNote->amount,
            'tax_amount' => $creditNote->tax_amount,
            'total_amount' => $creditNote->total_amount,
            'amount_in_words' => $this->convertToWords($creditNote->total_amount),
        ];
    }

    /**
     * Convert amount to words.
     */
    private function convertToWords(float $amount): string
    {
        // This would integrate with a number-to-words library
        // For now, I'll provide a simple implementation
        return $this->numberToWords($amount).' dollars';
    }

    /**
     * Simple number to words conversion.
     */
    private function numberToWords(float $number): string
    {
        // Simplified implementation - in production, use a proper library
        $ones = [
            0 => 'zero', 1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four',
            5 => 'five', 6 => 'six', 7 => 'seven', 8 => 'eight', 9 => 'nine',
        ];

        $tens = [
            1 => 'ten', 2 => 'twenty', 3 => 'thirty', 4 => 'forty',
            5 => 'fifty', 6 => 'sixty', 7 => 'seventy', 8 => 'eighty', 9 => 'ninety',
        ];

        $formatted = number_format($number, 2);
        $parts = explode('.', $formatted);

        $dollars = $this->convertWholeNumber((int) $parts[0]);
        $cents = $this->convertWholeNumber((int) $parts[1]);

        return trim($dollars.' '.$cents.'/100');
    }

    /**
     * Convert whole number to words.
     */
    private function convertWholeNumber(int $number): string
    {
        if ($number === 0) {
            return 'zero';
        }

        if ($number <= 99) {
            return $this->convertTwoDigitNumber($number);
        }

        // Simplified - in production use a proper library
        return (string) $number;
    }

    /**
     * Convert two-digit number to words.
     */
    private function convertTwoDigitNumber(int $number): string
    {
        $ones = [
            1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four',
            5 => 'five', 6 => 'six', 7 => 'seven', 8 => 'eight', 9 => 'nine',
        ];

        $teens = [
            11 => 'eleven', 12 => 'twelve', 13 => 'thirteen', 14 => 'fourteen',
            15 => 'fifteen', 16 => 'sixteen', 17 => 'seventeen', 18 => 'eighteen', 19 => 'nineteen',
        ];

        $tens = [
            10 => 'ten', 20 => 'twenty', 30 => 'thirty', 40 => 'forty',
            50 => 'fifty', 60 => 'sixty', 70 => 'seventy', 80 => 'eighty', 90 => 'ninety',
        ];

        if ($number <= 9) {
            return $ones[$number] ?? '';
        }

        if ($number >= 10 && $number <= 19) {
            return $teens[$number] ?? '';
        }

        $ten = floor($number / 10) * 10;
        $one = $number % 10;

        $result = $tens[$ten] ?? '';
        if ($one > 0) {
            $result .= ' '.$ones[$one];
        }

        return $result;
    }

    /**
     * Format notes for PDF.
     */
    private function formatNotes(?string $notes): ?string
    {
        return $notes ? nl2br(e($notes)) : null;
    }

    /**
     * Format terms for PDF.
     */
    private function formatTerms(?string $terms): ?string
    {
        return $terms ? nl2br(e($terms)) : null;
    }

    /**
     * Generate multiple credit notes PDFs.
     */
    public function generateBatchPdfs(array $creditNotes, User $user, array $options = []): array
    {
        $results = [];

        foreach ($creditNotes as $creditNote) {
            try {
                $path = $this->generateCreditNotePdf($creditNote, $user, $options);
                $results[] = [
                    'credit_note_id' => $creditNote->id,
                    'credit_note_number' => $creditNote->credit_note_number,
                    'path' => $path,
                    'success' => true,
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
     * Generate PDF for multiple credit notes in a single file.
     */
    public function generateCombinedPdf(array $creditNotes, User $user, array $options = []): string
    {
        if (empty($creditNotes)) {
            throw new \InvalidArgumentException('No credit notes provided for combined PDF');
        }

        $this->authService->canAccessCompany($user, $creditNotes[0]->company);
        $this->authService->hasPermission($user, 'credit_notes.pdf');

        // Prepare data for combined PDF
        $data = [
            'credit_notes' => $creditNotes,
            'company' => $creditNotes[0]->company,
            'generated_at' => now()->format('Y-m-d H:i:s'),
            'generated_by' => auth()->user()->name,
            'settings' => $this->getPdfSettings($creditNotes[0]->company),
        ];

        // Generate PDF
        $pdf = Pdf::loadView('pdf.credit-notes-combined', $data)
            ->setPaper($options['paper_size'] ?? 'a4')
            ->setOrientation($options['orientation'] ?? 'portrait');

        // Generate filename
        $date = now()->format('Y-m-d');
        $filename = "credit-notes-combined-{$date}.pdf";

        // Save to storage
        $disk = $options['disk'] ?? 'public';
        $directory = "companies/{$creditNotes[0]->company->id}/credit-notes";
        $path = "{$directory}/{$filename}";

        Storage::disk($disk)->put($path, $pdf->output());

        return $path;
    }
}
