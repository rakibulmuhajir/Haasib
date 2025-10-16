<?php

namespace Modules\Accounting\Domain\JournalEntries\Events;

use App\Models\JournalBatch;
use App\Models\JournalEntry;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EntryRemovedFromBatch
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public JournalBatch $batch,
        public JournalEntry $entry,
        public ?int $removedBy = null
    ) {}
}
