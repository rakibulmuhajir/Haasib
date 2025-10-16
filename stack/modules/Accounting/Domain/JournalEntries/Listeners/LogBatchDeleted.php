<?php

namespace Modules\Accounting\Domain\JournalEntries\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\Accounting\Domain\JournalEntries\Events\BatchDeleted;

class LogBatchDeleted
{
    public function handle(BatchDeleted $event): void
    {
        Log::info('Journal batch deleted', [
            'batch_id' => $event->batch->id,
            'company_id' => $event->batch->company_id,
            'batch_name' => $event->batch->name,
            'total_entries' => $event->batch->total_entries,
            'deleted_by' => $event->deletedBy,
            'timestamp' => now()->toISOString(),
        ]);
    }
}
