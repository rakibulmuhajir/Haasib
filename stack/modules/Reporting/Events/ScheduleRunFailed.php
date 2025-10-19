<?php

namespace Modules\Reporting\Events;

use Illuminate\Foundation\Events\Dispatchable;

class ScheduleRunFailed
{
    use Dispatchable;

    public function __construct(
        public string $companyId,
        public string $scheduleId,
        public \Throwable $exception
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [];
    }
}
