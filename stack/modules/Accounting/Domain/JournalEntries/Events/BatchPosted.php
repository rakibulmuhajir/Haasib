<?php

namespace Modules\Accounting\Domain\JournalEntries\Events;

use App\Models\JournalBatch;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BatchPosted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public JournalBatch $batch,
        public ?int $postedBy = null
    ) {}
}
