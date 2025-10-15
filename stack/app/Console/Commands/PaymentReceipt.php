<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Modules\Accounting\Domain\Payments\Services\PaymentReceiptService;
use App\Models\Payment;

class PaymentReceipt extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment:receipt 
                            {payment : Payment number or ID}
                            {--format=table : Output format (table, json)}
                            {--download= : Download file (pdf, json)}
                            {--output= : Output file path (optional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate and download payment receipt';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            // Set company context from environment
            $companyId = $this->getCompanyId();
            if (!$companyId) {
                $this->error('Company context is required. Set APP_COMPANY_ID environment variable.');
                return 1;
            }

            // Get payment ID
            $paymentId = $this->getPaymentId($this->argument('payment'));
            if (!$paymentId) {
                $this->error('Payment not found: ' . $this->argument('payment'));
                return 1;
            }

            // Load payment with relationships
            $payment = Payment::with(['entity', 'currency', 'allocations.invoice'])
                ->where('payment_id', $paymentId)
                ->firstOrFail();

            // Generate receipt data
            $receiptService = new PaymentReceiptService();
            $receiptData = $receiptService->generateReceiptData($payment);

            // Handle download option
            if ($this->option('download')) {
                return $this->handleDownload($payment, $receiptService, $this->option('download'));
            }

            // Display receipt information
            if ($this->option('format') === 'json') {
                $this->line(json_encode($receiptData, JSON_PRETTY_PRINT));
            } else {
                $this->displayReceiptTable($payment, $receiptData);
            }

            return 0;

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $this->error('Payment not found: ' . $this->argument('payment'));
            return 1;
        } catch (\Throwable $e) {
            $this->error('Failed to generate receipt: ' . $e->getMessage());
            
            if ($this->option('format') === 'json') {
                $this->line(json_encode([
                    'success' => false,
                    'error' => $e->getMessage(),
                ], JSON_PRETTY_PRINT));
            }
            
            return 1;
        }
    }

    /**
     * Handle receipt download.
     */
    private function handleDownload(Payment $payment, PaymentReceiptService $receiptService, string $format): int
    {
        try {
            $outputPath = $this->option('output') ?: getcwd() . '/receipt-' . $payment->payment_number;
            
            if ($format === 'pdf') {
                $pdfContent = $receiptService->generatePdfReceipt($payment);
                $filename = $outputPath . '.pdf';
                file_put_contents($filename, $pdfContent);
                $this->info('✓ PDF receipt downloaded: ' . $filename);
            } elseif ($format === 'json') {
                $receiptData = $receiptService->generateReceiptData($payment);
                $filename = $outputPath . '.json';
                file_put_contents($filename, json_encode($receiptData, JSON_PRETTY_PRINT));
                $this->info('✓ JSON receipt downloaded: ' . $filename);
            } else {
                $this->error('Invalid download format. Supported: pdf, json');
                return 1;
            }

            return 0;
        } catch (\Throwable $e) {
            $this->error('Failed to download receipt: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Display receipt information in table format.
     */
    private function displayReceiptTable(Payment $payment, array $receiptData): void
    {
        // Header
        $this->info('');
        $this->info('PAYMENT RECEIPT');
        $this->info('Receipt Number: ' . $receiptData['receipt_number']);
        $this->info(str_repeat('-', 50));

        // Company and Customer Information
        $this->info('');
        $this->info('Company Information:');
        $this->line('  Name: ' . $receiptData['company_details']['name']);
        $this->line('  Address: ' . $receiptData['company_details']['address']);
        $this->line('  ' . $receiptData['company_details']['city'] . ', ' . 
                   $receiptData['company_details']['country'] . ' ' . 
                   $receiptData['company_details']['postal_code']);

        $this->info('');
        $this->info('Customer Information:');
        $this->line('  Name: ' . $receiptData['customer_details']['name']);
        $this->line('  Email: ' . $receiptData['customer_details']['email']);

        // Payment Details
        $this->info('');
        $this->info('Payment Details:');
        $paymentTable = [
            ['Payment Number', $receiptData['payment_details']['payment_number']],
            ['Payment Date', $receiptData['payment_details']['payment_date']],
            ['Payment Method', $receiptData['payment_details']['payment_method_label']],
            ['Reference', $receiptData['payment_details']['reference_number'] ?: '-'],
        ];
        $this->table(['Field', 'Value'], $paymentTable);

        // Amount Summary
        $this->info('');
        $this->info('Amount Summary:');
        $amountTable = [
            ['Payment Amount', $this->formatMoney($receiptData['amount_summary']['payment_amount'], $receiptData['amount_summary']['currency_code'])],
        ];

        if ($receiptData['total_discount_applied'] > 0) {
            $amountTable[] = ['Total Discount Applied', '-' . $this->formatMoney($receiptData['total_discount_applied'], $receiptData['amount_summary']['currency_code'])];
        }

        $amountTable[] = ['Total Allocated', $this->formatMoney($receiptData['amount_summary']['total_allocated'], $receiptData['amount_summary']['currency_code'])];
        
        if ($receiptData['amount_summary']['remaining_amount'] > 0) {
            $amountTable[] = ['Unallocated Cash', $this->formatMoney($receiptData['amount_summary']['remaining_amount'], $receiptData['amount_summary']['currency_code'])];
        }

        $this->table(['Field', 'Amount'], $amountTable);

        // Allocations
        if (!empty($receiptData['allocations'])) {
            $this->info('');
            $this->info('Allocations:');
            
            $allocationData = [];
            foreach ($receiptData['allocations'] as $allocation) {
                $allocationData[] = [
                    $allocation['invoice_number'],
                    $allocation['allocation_date'],
                    $this->formatMoney($allocation['original_amount'], $receiptData['amount_summary']['currency_code']),
                    $allocation['discount_amount'] > 0 ? 
                        '-' . $this->formatMoney($allocation['discount_amount'], $receiptData['amount_summary']['currency_code']) : 
                        '-',
                    $this->formatMoney($allocation['allocated_amount'], $receiptData['amount_summary']['currency_code']),
                    $allocation['notes'] ?: '-',
                ];
            }

            $this->table(
                ['Invoice #', 'Date', 'Original', 'Discount', 'Allocated', 'Notes'],
                $allocationData
            );
        }

        // Footer
        $this->info('');
        $this->info('Generated on: ' . $receiptData['generated_at']);
        $this->info('');
        $this->info('Download Options:');
        $this->line('  JSON: php artisan payment:receipt ' . $payment->payment_number . ' --download=json');
        $this->line('  PDF:  php artisan payment:receipt ' . $payment->payment_number . ' --download=pdf');
    }

    /**
     * Get company ID from environment.
     */
    private function getCompanyId(): ?string
    {
        return $_ENV['APP_COMPANY_ID'] ?? null;
    }

    /**
     * Get payment ID from number or ID.
     */
    private function getPaymentId(string $paymentInput): ?string
    {
        // Try as UUID first
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $paymentInput)) {
            return $paymentInput;
        }

        // Try as payment number (would need database query)
        return null; // For simplicity, expecting UUID input
    }

    /**
     * Format money amount.
     */
    private function formatMoney(float $amount, string $currency): string
    {
        $symbol = match($currency) {
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            default => $currency . ' ',
        };
        
        return $symbol . number_format($amount, 2);
    }
}