<?php

namespace Modules\Ledger\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Ledger\Domain\PeriodClose\Models\PeriodCloseTemplate;

class PeriodCloseTemplateUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public PeriodCloseTemplate $template,
        public array $changes,
        public bool $tasksUpdated,
        public User $user
    ) {}
}
