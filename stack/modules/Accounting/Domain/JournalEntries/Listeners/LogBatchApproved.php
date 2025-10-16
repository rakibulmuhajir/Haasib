<?php

namespace Modules\Accounting\Domain\JournalEntries\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\Accounting\Domain\JournalEntries\Events\BatchApproved;

class LogBatchApproved
{
    public function handle(BatchApproved $event): void
    {
        Log::info('Journal batch approved', [
            'batch_id' => $event->batch->id,
            'company_id' => $event->batch->company_id,
            'batch_name' => $event->batch->name,
            'total_entries' => $event->batch->total_entries,
            'approved_by' => $event->approvedBy,
            'approved_at' => $event->batch->approved_at,
            'timestamp' => now()->toISOString(),
        ]);
    }
}
