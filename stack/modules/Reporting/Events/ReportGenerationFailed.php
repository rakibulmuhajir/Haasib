<?php

namespace Modules\Reporting\Events;

use Illuminate\Foundation\Events\Dispatchable;

class ReportGenerationFailed
{
    use Dispatchable;

    public function __construct(
        public string $companyId,
        public string $reportId,
        public string $reportType,
        public \Throwable $exception,
        public int $attempts
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [];
    }
}
