<?php

namespace Modules\Reporting\Events;

use Illuminate\Foundation\Events\Dispatchable;

class ReportGenerated
{
    use Dispatchable;

    public function __construct(
        public string $companyId,
        public string $reportId,
        public string $reportType,
        public int $durationSeconds
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [];
    }
}
