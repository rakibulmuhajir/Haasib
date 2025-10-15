<?php

namespace Modules\Accounting\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Bus;
use Modules\Accounting\Domain\Payments\Events\PaymentBatchProcessed;
use Modules\Accounting\Domain\Payments\Events\PaymentBatchFailed;
use Modules\Accounting\Domain\Payments\Telemetry\PaymentMetrics;
use App\Models\PaymentBatch;
use App\Models\Payment;

class ProcessPaymentBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300; // 5 minutes

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $batchId,
        public string $companyId
    ) {
        $this->onQueue('payment-processing');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Set company context for RLS
        DB::statement("SET app.current_company = ?", [$this->companyId]);

        $batch = PaymentBatch::findOrFail($this->batchId);

        if (!$batch->canBeProcessed()) {
            Log::warning("Batch {$batch->id} cannot be processed. Current status: {$batch->status}");
            return;
        }

        try {
            $batch->markAsProcessing();

            $sourceType = $batch->source_type;
            $processedCount = 0;
            $failedCount = 0;
            $processedAmount = 0.00;

            if ($sourceType === 'csv_import') {
                [$processedCount, $failedCount, $processedAmount] = $this->processCsvBatch($batch);
            } else {
                [$processedCount, $failedCount, $processedAmount] = $this->processManualBatch($batch);
            }

            // Update batch with final statistics
            $batch->update([
                'receipt_count' => $processedCount + $failedCount,
                'total_amount' => $processedAmount,
            ]);

            if ($failedCount > 0 && $processedCount === 0) {
                // Complete failure
                $batch->markAsFailed('processing_errors', [
                    'total_errors' => $failedCount,
                    'error_summary' => 'All payments failed to process'
                ]);

                event(new PaymentBatchFailed([
                    'batch_id' => $batch->id,
                    'company_id' => $this->companyId,
                    'error_type' => 'processing_errors',
                    'error_details' => [
                        'total_errors' => $failedCount,
                        'error_summary' => 'All payments failed to process'
                    ],
                    'processed_count' => $processedCount,
                    'failed_count' => $failedCount,
                    'processed_amount' => $processedAmount
                ]));

                PaymentMetrics::batchFailed($this->companyId, $sourceType, $failedCount);

            } else {
                // Success or partial success
                $status = ($failedCount > 0) ? 'completed_with_errors' : 'completed';
                $batch->markAsCompleted([
                    'processed_count' => $processedCount,
                    'failed_count' => $failedCount,
                    'processed_amount' => $processedAmount
                ]);

                event(new PaymentBatchProcessed([
                    'batch_id' => $batch->id,
                    'company_id' => $this->companyId,
                    'status' => $status,
                    'processed_count' => $processedCount,
                    'failed_count' => $failedCount,
                    'processed_amount' => $processedAmount
                ]));

                PaymentMetrics::batchProcessed($this->companyId, $sourceType, $processedCount, $processedAmount);
                
                // Record processing time
                if ($batch->processing_started_at && $batch->processing_finished_at) {
                    $processingTime = $batch->processing_started_at->diffInSeconds($batch->processing_finished_at);
                    PaymentMetrics::batchProcessingTime($this->companyId, $sourceType, $processingTime);
                }
                
                if ($failedCount > 0) {
                    PaymentMetrics::batchErrors($this->companyId, $sourceType, $failedCount);
                }
            }

        } catch (\Throwable $e) {
            Log::error("Batch processing failed for batch {$batch->id}: " . $e->getMessage(), [
                'batch_id' => $batch->id,
                'company_id' => $this->companyId,
                'exception' => $e
            ]);

            $batch->markAsFailed('system_error', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString()
            ]);

            event(new PaymentBatchFailed([
                'batch_id' => $batch->id,
                'company_id' => $this->companyId,
                'error_type' => 'system_error',
                'error_details' => [
                    'error_message' => $e->getMessage(),
                    'error_trace' => $e->getTraceAsString()
                ],
                'processed_count' => 0,
                'failed_count' => $batch->receipt_count,
                'processed_amount' => 0.00
            ]));

            PaymentMetrics::batchFailed($this->companyId, $sourceType, $batch->receipt_count);

            throw $e;
        }
    }

    /**
     * Process a CSV batch.
     */
    private function processCsvBatch(PaymentBatch $batch): array
    {
        $metadata = $batch->metadata;
        $filePath = $metadata['file_path'] ?? null;

        if (!$filePath || !Storage::disk('local')->exists($filePath)) {
            throw new \InvalidArgumentException('CSV file not found');
        }

        // Parse and validate CSV
        $createBatchAction = new \Modules\Accounting\Domain\Payments\Actions\CreatePaymentBatchAction();
        $entries = $createBatchAction->parseAndValidateCsv($filePath);
        $validation = $createBatchAction->validateCsvEntries($entries, $this->companyId);

        if ($validation['error_count'] > 0) {
            // Store validation errors in batch metadata
            $batchMetadata = $batch->metadata;
            $batchMetadata['validation_errors'] = $validation['errors'];
            $batch->update(['metadata' => $batchMetadata]);
        }

        return $this->processBatchEntries($batch, $validation['valid_entries']);
    }

    /**
     * Process a manual/bank feed batch.
     */
    private function processManualBatch(PaymentBatch $batch): array
    {
        $entries = $batch->metadata['entries'] ?? [];
        
        return $this->processBatchEntries($batch, $entries);
    }

    /**
     * Process individual payment entries.
     */
    private function processBatchEntries(PaymentBatch $batch, array $entries): array
    {
        $processedCount = 0;
        $failedCount = 0;
        $processedAmount = 0.00;
        $errors = [];

        foreach ($entries as $index => $entry) {
            try {
                // Prepare payment data
                $paymentData = [
                    'entity_id' => $entry['entity_id'] ?? $entry['customer_id'],
                    'payment_method' => $entry['payment_method'],
                    'amount' => (float) $entry['amount'],
                    'currency_id' => $entry['currency_id'] ?? $this->getDefaultCurrency(),
                    'payment_date' => $entry['payment_date'],
                    'reference_number' => $entry['reference_number'] ?? null,
                    'notes' => $entry['notes'] ?? null,
                    'auto_allocate' => $entry['auto_allocate'] ?? false,
                    'allocation_strategy' => $entry['allocation_strategy'] ?? 'fifo',
                    'batch_id' => $batch->id,
                    'company_id' => $this->companyId,
                    'created_by_user_id' => $batch->created_by_user_id,
                ];

                // Process payment through command bus
                $result = Bus::dispatch('payment.create', $paymentData);

                $processedCount++;
                $processedAmount += (float) $entry['amount'];

                // Update progress
                if ($processedCount % 10 === 0) {
                    $batch->updateProgress($processedCount, $failedCount);
                }

            } catch (\Throwable $e) {
                $failedCount++;
                $rowIdentifier = $entry['_row_number'] ?? ($index + 1);
                $errors["row_{$rowIdentifier}"] = $e->getMessage();

                Log::warning("Failed to process payment entry {$rowIdentifier} in batch {$batch->id}: " . $e->getMessage());

                // Continue processing other entries
                continue;
            }
        }

        // Store errors in batch metadata if any
        if (!empty($errors)) {
            $batchMetadata = $batch->metadata;
            $batchMetadata['processing_errors'] = $errors;
            $batch->update(['metadata' => $batchMetadata]);
        }

        // Final progress update
        $batch->updateProgress($processedCount, $failedCount);

        return [$processedCount, $failedCount, $processedAmount];
    }

    /**
     * Get default currency for company.
     */
    private function getDefaultCurrency(): string
    {
        // This should come from company settings
        // For now, return USD as default
        return 'USD';
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Payment batch job failed permanently", [
            'batch_id' => $this->batchId,
            'company_id' => $this->companyId,
            'exception' => $exception,
            'attempt' => $this->attempts()
        ]);

        try {
            $batch = PaymentBatch::find($this->batchId);
            if ($batch && $batch->canBeProcessed()) {
                $batch->markAsFailed('job_failure', [
                    'error_message' => $exception->getMessage(),
                    'attempts' => $this->attempts()
                ]);
            }
        } catch (\Throwable $e) {
            Log::error("Failed to update batch status after job failure", [
                'batch_id' => $this->batchId,
                'exception' => $e
            ]);
        }
    }
}