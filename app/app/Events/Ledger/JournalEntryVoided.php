<?php

namespace App\Events\Ledger;

use App\Models\JournalEntry;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JournalEntryVoided
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public JournalEntry $journalEntry,
        public array $context = []
    ) {
        //
    }
}
