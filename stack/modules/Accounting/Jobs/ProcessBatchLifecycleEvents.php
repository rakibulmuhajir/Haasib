<?php

namespace Modules\Accounting\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Accounting\Domain\JournalEntries\Events\BatchApproved;
use Modules\Accounting\Domain\JournalEntries\Events\BatchCreated;
use Modules\Accounting\Domain\JournalEntries\Events\BatchDeleted;
use Modules\Accounting\Domain\JournalEntries\Events\BatchPosted;
use Modules\Accounting\Domain\JournalEntries\Events\EntryAddedToBatch;
use Modules\Accounting\Domain\JournalEntries\Events\EntryRemovedFromBatch;

class ProcessBatchLifecycleEvents implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public BatchCreated|BatchApproved|BatchPosted|BatchDeleted|EntryAddedToBatch|EntryRemovedFromBatch $event
    ) {}

    public function handle(): void
    {
        // The actual event handling is delegated to the event listeners
        // This job ensures that event processing is queued and can be retried on failure
        event($this->event);
    }

    public function failed(\Throwable $exception): void
    {
        $eventClass = class_basename($this->event);

        \Log::error('Batch lifecycle event processing failed', [
            'event_type' => $eventClass,
            'batch_id' => $this->event->batch->id ?? null,
            'company_id' => $this->event->batch->company_id ?? null,
            'error' => $exception->getMessage(),
            'attempt' => $this->attempts(),
            'max_tries' => $this->tries,
        ]);
    }
}
