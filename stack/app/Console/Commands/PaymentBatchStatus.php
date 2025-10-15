<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\PaymentBatch;

class PaymentBatchStatus extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'payment:batch:status 
                            {batch-id : Batch ID or batch number}
                            {--format=table : Output format (table, json)}
                            {--refresh : Continuously refresh status every 5 seconds}
                            {--payments : Show associated payments}';

    /**
     * The console command description.
     */
    protected $description = 'Check the status of a payment batch';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $batchId = $this->argument('batch-id');
        $format = $this->option('format');
        $refresh = $this->option('refresh');
        $showPayments = $this->option('payments');

        try {
            // Set company context
            $companyId = $this->getCompanyId();
            if (!$companyId) {
                $this->error('Company context is required. Set APP_COMPANY_ID environment variable.');
                return 1;
            }

            DB::statement("SET app.current_company = ?", [$companyId]);

            do {
                $batch = $this->findBatch($batchId);
                $this->displayBatchStatus($batch, $format, $showPayments);

                if ($refresh && $batch->isProcessing()) {
                    $this->info("\nRefreshing in 5 seconds... (Press Ctrl+C to stop)");
                    sleep(5);
                }

            } while ($refresh && $batch->isProcessing());

            return 0;

        } catch (\Throwable $e) {
            $this->error("Failed to get batch status: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Find batch by ID or number.
     */
    private function findBatch(string $batchId): PaymentBatch
    {
        // Try by UUID first
        if ($this->isUuid($batchId)) {
            $batch = PaymentBatch::find($batchId);
            if ($batch) {
                return $batch;
            }
        }

        // Try by batch number
        $batch = PaymentBatch::where('batch_number', $batchId)->first();
        if ($batch) {
            return $batch;
        }

        throw new \Exception("Batch not found: {$batchId}");
    }

    /**
     * Display batch status.
     */
    private function displayBatchStatus(PaymentBatch $batch, string $format, bool $showPayments): void
    {
        if ($format === 'json') {
            $this->line(json_encode($this->formatBatchData($batch, $showPayments), JSON_PRETTY_PRINT));
            return;
        }

        // Clear screen for refresh mode
        if ($this->option('refresh')) {
            system('clear');
        }

        $this->info("📋 Batch Status Report");
        $this->info("==================");

        $this->table(
            ['Field', 'Value'],
            [
                ['Batch ID', $batch->id],
                ['Batch Number', $batch->batch_number],
                ['Status', $batch->status_label],
                ['Source Type', $batch->source_type],
                ['Receipt Count', $batch->receipt_count],
                ['Total Amount', $batch->currency . ' ' . number_format($batch->total_amount, 2)],
                ['Progress', $batch->progress_percentage . '%'],
                ['Created By', $batch->creator?->name ?? 'N/A'],
                ['Created At', $batch->created_at->format('Y-m-d H:i:s')],
                ['Processing Started', $batch->processing_started_at?->format('Y-m-d H:i:s') ?? 'Not started'],
                ['Processed At', $batch->processed_at?->format('Y-m-d H:i:s') ?? 'Not processed'],
            ]
        );

        // Display notes if present
        if ($batch->notes) {
            $this->info("\n📝 Notes:");
            $this->line($batch->notes);
        }

        // Display processing statistics
        $metadata = $batch->metadata ?? [];
        if (isset($metadata['processed_count']) || isset($metadata['failed_count'])) {
            $this->info("\n📊 Processing Statistics:");
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Processed', $metadata['processed_count'] ?? 0],
                    ['Failed', $metadata['failed_count'] ?? 0],
                    ['Success Rate', $this->calculateSuccessRate($metadata) . '%'],
                ]
            );
        }

        // Display errors if batch failed
        if ($batch->hasFailed()) {
            $this->displayBatchErrors($batch);
        }

        // Display estimated completion time
        if ($batch->isProcessing() && $batch->estimated_completion) {
            $eta = \Carbon\Carbon::parse($batch->estimated_completion);
            $this->info("\n⏰ Estimated Completion: " . $eta->format('Y-m-d H:i:s'));
        }

        // Display payments if requested
        if ($showPayments && $batch->hasPayments()) {
            $this->displayPayments($batch);
        }

        // Display next steps
        $this->displayNextSteps($batch);
    }

    /**
     * Display batch errors.
     */
    private function displayBatchErrors(PaymentBatch $batch): void
    {
        $this->error("\n❌ Batch Processing Errors:");
        $this->error("Error Type: " . ($batch->getErrorType() ?? 'Unknown'));
        
        $errors = $batch->getErrorDetails();
        if (!empty($errors)) {
            $this->error("Error Details:");
            foreach ($errors as $key => $error) {
                if (is_array($error)) {
                    $error = implode(', ', $error);
                }
                $this->error("  {$key}: {$error}");
            }
        }
    }

    /**
     * Display payments for the batch.
     */
    private function displayPayments(PaymentBatch $batch): void
    {
        $this->info("\n💳 Associated Payments:");
        
        $payments = $batch->payments()->take(20)->get();
        
        if ($payments->isEmpty()) {
            $this->line("No payments found (still processing)");
            return;
        }

        $paymentData = $payments->map(function ($payment) {
            return [
                'Payment #' => $payment->payment_number,
                'Amount' => $payment->currency . ' ' . number_format($payment->amount, 2),
                'Method' => $payment->payment_method_label,
                'Date' => $payment->payment_date,
                'Status' => $payment->status_label,
                'Entity' => $payment->entity?->name ?? 'N/A',
            ];
        });

        $this->table(
            ['Payment #', 'Amount', 'Method', 'Date', 'Status', 'Entity'],
            $paymentData->toArray()
        );

        if ($batch->payments()->count() > 20) {
            $total = $batch->payments()->count();
            $this->info("... and " . ($total - 20) . " more payments");
        }
    }

    /**
     * Display next steps based on batch status.
     */
    private function displayNextSteps(PaymentBatch $batch): void
    {
        $this->info("\n🎯 Next Steps:");

        if ($batch->isProcessing()) {
            $this->info("• Wait for processing to complete");
            $this->info("• Check status again with: php artisan payment:batch:status {$batch->batch_number}");
            $this->info("• View audit trail in the UI");
        } elseif ($batch->isCompleted()) {
            $this->info("• ✅ Batch completed successfully");
            $this->info("• Review created payments in the payments module");
            $this->info("• View batch audit trail");
        } elseif ($batch->hasFailed()) {
            $this->info("• ❌ Batch processing failed");
            $this->info("• Review error details above");
            $this->info("• Fix data issues and retry import");
            $this->info("• Contact support if errors persist");
        } else {
            $this->info("• Batch is pending processing");
            $this->info("• Processing should start automatically");
        }
    }

    /**
     * Format batch data for JSON output.
     */
    private function formatBatchData(PaymentBatch $batch, bool $showPayments): array
    {
        $data = [
            'batch_id' => $batch->id,
            'batch_number' => $batch->batch_number,
            'status' => $batch->status,
            'status_label' => $batch->status_label,
            'source_type' => $batch->source_type,
            'receipt_count' => $batch->receipt_count,
            'total_amount' => $batch->total_amount,
            'currency' => $batch->currency,
            'progress_percentage' => $batch->progress_percentage,
            'created_at' => $batch->created_at->toISOString(),
            'processing_started_at' => $batch->processing_started_at?->toISOString(),
            'processed_at' => $batch->processed_at?->toISOString(),
            'processing_finished_at' => $batch->processing_finished_at?->toISOString(),
            'estimated_completion' => $batch->estimated_completion,
            'notes' => $batch->notes,
            'metadata' => $batch->metadata,
            'created_by' => $batch->creator?->name,
            'has_payments' => $batch->hasPayments(),
        ];

        // Add error details if failed
        if ($batch->hasFailed()) {
            $data['error_type'] = $batch->getErrorType();
            $data['error_details'] = $batch->getErrorDetails();
        }

        // Add payments if requested
        if ($showPayments && $batch->hasPayments()) {
            $data['payments'] = $batch->payments()->get()->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'payment_number' => $payment->payment_number,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency->code,
                    'payment_method' => $payment->payment_method,
                    'payment_date' => $payment->payment_date,
                    'status' => $payment->status,
                    'entity_name' => $payment->entity?->name,
                    'created_at' => $payment->created_at->toISOString(),
                ];
            })->toArray();
        }

        return $data;
    }

    /**
     * Calculate success rate.
     */
    private function calculateSuccessRate(array $metadata): string
    {
        $processed = $metadata['processed_count'] ?? 0;
        $failed = $metadata['failed_count'] ?? 0;
        $total = $processed + $failed;

        if ($total === 0) {
            return '0.0';
        }

        return number_format(($processed / $total) * 100, 1);
    }

    /**
     * Check if string is a UUID.
     */
    private function isUuid(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) === 1;
    }

    /**
     * Get company ID from environment.
     */
    private function getCompanyId(): ?string
    {
        return env('APP_COMPANY_ID');
    }
}