<?php

namespace Modules\Reporting\Events;

use Illuminate\Foundation\Events\Dispatchable;

class ScheduleRunCompleted
{
    use Dispatchable;

    public function __construct(
        public string $companyId,
        public string $scheduleId,
        public string $reportId
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [];
    }
}
