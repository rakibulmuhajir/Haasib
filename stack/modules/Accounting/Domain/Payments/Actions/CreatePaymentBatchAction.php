<?php

namespace Modules\Accounting\Domain\Payments\Actions;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Modules\Accounting\Domain\Payments\Events\PaymentBatchCreated;
use Modules\Accounting\Domain\Payments\Telemetry\PaymentMetrics;
use App\Models\PaymentBatch;

class CreatePaymentBatchAction
{
    /**
     * Execute payment batch creation.
     */
    public function execute(array $data): array
    {
        // Validate input data
        $validated = Validator::make($data, [
            'source_type' => 'required|string|in:manual,csv_import,bank_feed',
            'file' => 'required_if:source_type,csv_import|file|mimes:csv,txt|max:10240', // 10MB max
            'entries' => 'required_if:source_type,manual,bank_feed|array|min:1',
            'entries.*.entity_id' => 'required|uuid|exists:hrm.customers,customer_id',
            'entries.*.payment_method' => 'required|string|in:cash,bank_transfer,card,cheque,other',
            'entries.*.amount' => 'required|numeric|min:0.01',
            'entries.*.currency_id' => 'required|uuid|exists:public.currencies,id',
            'entries.*.payment_date' => 'required|date',
            'entries.*.reference_number' => 'nullable|string|max:100',
            'entries.*.notes' => 'nullable|string',
            'entries.*.auto_allocate' => 'boolean',
            'entries.*.allocation_strategy' => 'nullable|string|in:fifo,proportional,overdue_first,largest_first,percentage_based,custom_priority',
            'notes' => 'nullable|string|max:1000',
            'metadata' => 'nullable|array',
        ])->validate();

        return DB::transaction(function () use ($validated) {
            $sourceType = $validated['source_type'];
            $companyId = $validated['company_id'];
            $createdBy = $validated['created_by_user_id'];

            // Generate unique batch number
            $batchNumber = $this->generateBatchNumber($companyId);

            // Prepare batch metadata
            $metadata = $validated['metadata'] ?? [];
            $metadata['source_type'] = $sourceType;

            // Calculate batch totals
            [$receiptCount, $totalAmount, $currency] = $this->calculateBatchTotals($validated, $sourceType);

            // Create batch record
            $batch = PaymentBatch::create([
                'id' => Str::uuid(),
                'company_id' => $companyId,
                'batch_number' => $batchNumber,
                'status' => 'pending',
                'receipt_count' => $receiptCount,
                'total_amount' => $totalAmount,
                'currency' => $currency,
                'created_by_user_id' => $createdBy,
                'notes' => $validated['notes'] ?? null,
                'metadata' => $metadata,
            ]);

            // Handle file processing for CSV imports
            if ($sourceType === 'csv_import') {
                $this->processCsvFile($batch, $validated['file']);
            } else {
                // Store manual/bank feed entries for processing
                $this->storeBatchEntries($batch, $validated['entries']);
            }

            // Dispatch batch processing job
            Queue::push(\Modules\Accounting\Jobs\ProcessPaymentBatch::class, [
                'batch_id' => $batch->id,
                'company_id' => $companyId,
            ]);

            // Record batch creation metrics
            PaymentMetrics::batchCreated($companyId, $sourceType, $receiptCount, $totalAmount);

            // Emit batch created event
            event(new PaymentBatchCreated([
                'batch_id' => $batch->id,
                'company_id' => $companyId,
                'batch_number' => $batch->batch_number,
                'source_type' => $sourceType,
                'receipt_count' => $receiptCount,
                'total_amount' => $totalAmount,
                'currency' => $currency,
                'created_by_user_id' => $createdBy,
                'metadata' => $metadata,
                'timestamp' => now()->toISOString(),
            ]));

            return [
                'batch_id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'status' => $batch->status,
                'receipt_count' => $receiptCount,
                'total_amount' => $totalAmount,
                'currency' => $currency,
                'estimated_completion' => $batch->estimated_completion,
                'message' => 'Batch accepted for processing',
                'created_at' => $batch->created_at->toISOString(),
            ];
        });
    }

    /**
     * Generate unique batch number for company.
     */
    private function generateBatchNumber(string $companyId): string
    {
        $today = now()->format('Ymd');
        $sequence = PaymentBatch::where('company_id', $companyId)
            ->where('batch_number', 'like', "BATCH-{$today}-%")
            ->count() + 1;
            
        return "BATCH-{$today}-" . str_pad($sequence, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Calculate batch totals from entries or file.
     */
    private function calculateBatchTotals(array $validated, string $sourceType): array
    {
        if ($sourceType === 'csv_import') {
            // For CSV files, we'll estimate totals from file
            $file = $validated['file'];
            $filePath = $file->store('batch-imports', 'local');
            
            $receiptCount = $this->estimateCsvRowCount($filePath);
            return [$receiptCount, 0.00, 'USD']; // Will be updated during processing
        }

        $entries = $validated['entries'];
        $receiptCount = count($entries);
        $totalAmount = array_sum(array_column($entries, 'amount'));
        
        // Use currency from first entry (assuming same currency for now)
        $currency = 'USD'; // This should come from company settings or first entry
        
        return [$receiptCount, $totalAmount, $currency];
    }

    /**
     * Process uploaded CSV file.
     */
    private function processCsvFile(PaymentBatch $batch, $file): void
    {
        $originalFilename = $file->getClientOriginalName();
        $fileHash = hash_file('sha256', $file->path());
        
        // Store file for processing
        $filePath = $file->store('batch-imports/' . $batch->id, 'local');
        
        // Update batch metadata with file info
        $metadata = $batch->metadata ?? [];
        $metadata['original_filename'] = $originalFilename;
        $metadata['file_hash'] = $fileHash;
        $metadata['file_path'] = $filePath;
        $metadata['file_size'] = $file->getSize();
        
        $batch->update(['metadata' => $metadata]);
    }

    /**
     * Store manual/bank feed entries for processing.
     */
    private function storeBatchEntries(PaymentBatch $batch, array $entries): void
    {
        $metadata = $batch->metadata ?? [];
        $metadata['entries'] = $entries;
        
        $batch->update(['metadata' => $metadata]);
    }

    /**
     * Estimate row count in CSV file.
     */
    private function estimateCsvRowCount(string $filePath): int
    {
        $handle = fopen(storage_path('app/' . $filePath), 'r');
        if (!$handle) {
            return 0;
        }

        $count = 0;
        while (($line = fgetcsv($handle)) !== false) {
            $count++;
        }
        
        fclose($handle);
        
        // Subtract header row
        return max(0, $count - 1);
    }
}