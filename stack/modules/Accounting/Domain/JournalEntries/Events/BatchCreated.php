<?php

namespace Modules\Accounting\Domain\JournalEntries\Events;

use App\Models\JournalBatch;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BatchCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public JournalBatch $batch,
        public ?int $createdBy = null
    ) {}
}
