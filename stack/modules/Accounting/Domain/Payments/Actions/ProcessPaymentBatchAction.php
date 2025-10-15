<?php

namespace Modules\Accounting\Domain\Payments\Actions;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Queue;
use Modules\Accounting\Jobs\ProcessPaymentBatch;
use App\Models\PaymentBatch;

class ProcessPaymentBatchAction
{
    /**
     * Execute payment batch processing by dispatching the job.
     */
    public function execute(string $batchId): array
    {
        // Validate input data
        $validated = Validator::make(['batch_id' => $batchId], [
            'batch_id' => 'required|uuid|exists:payment_receipt_batches,id',
        ])->validate();

        // Find the batch
        $batch = PaymentBatch::findOrFail($batchId);
        
        // Check if batch can be processed
        if (!$batch->canBeProcessed()) {
            return [
                'batch_id' => $batchId,
                'status' => $batch->status,
                'message' => 'Batch cannot be processed. Current status: ' . $batch->status,
                'error' => true,
            ];
        }

        // Dispatch the job for background processing
        $job = new ProcessPaymentBatch($batchId, $batch->company_id);
        dispatch($job);

        return [
            'batch_id' => $batchId,
            'status' => 'processing',
            'message' => 'Batch processing started',
            'job_id' => $job->getJobId(),
            'estimated_completion' => $batch->estimated_completion,
        ];
    }
}