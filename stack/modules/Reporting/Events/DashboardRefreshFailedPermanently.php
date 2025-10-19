<?php

namespace Modules\Reporting\Events;

use Illuminate\Foundation\Events\Dispatchable;

class DashboardRefreshFailedPermanently
{
    use Dispatchable;

    public function __construct(
        public string $companyId,
        public string $layoutId,
        public array $parameters,
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
