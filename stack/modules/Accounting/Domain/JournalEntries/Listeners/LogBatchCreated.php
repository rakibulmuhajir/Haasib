<?php

namespace Modules\Accounting\Domain\JournalEntries\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\Accounting\Domain\JournalEntries\Events\BatchCreated;

class LogBatchCreated
{
    public function handle(BatchCreated $event): void
    {
        Log::info('Journal batch created', [
            'batch_id' => $event->batch->id,
            'company_id' => $event->batch->company_id,
            'batch_name' => $event->batch->name,
            'total_entries' => $event->batch->total_entries,
            'created_by' => $event->createdBy,
            'timestamp' => now()->toISOString(),
        ]);
    }
}
