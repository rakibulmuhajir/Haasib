<?php

namespace Modules\Ledger\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Ledger\Domain\PeriodClose\Models\PeriodClose;
use Modules\Ledger\Domain\PeriodClose\Models\PeriodCloseTemplate;

class PeriodCloseTemplateSynced
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public PeriodClose $periodClose,
        public PeriodCloseTemplate $template,
        public int $syncedTasksCount,
        public User $user
    ) {}
}
