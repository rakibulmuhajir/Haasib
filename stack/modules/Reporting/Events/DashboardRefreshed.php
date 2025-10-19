<?php

namespace Modules\Reporting\Events;

use Illuminate\Foundation\Events\Dispatchable;

class DashboardRefreshed
{
    use Dispatchable;

    public function __construct(
        public string $companyId,
        public string $layoutId,
        public array $parameters,
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
