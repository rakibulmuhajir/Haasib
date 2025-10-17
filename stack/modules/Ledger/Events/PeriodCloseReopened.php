<?php

namespace Modules\Ledger\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Ledger\Domain\PeriodClose\Models\PeriodClose;

class PeriodCloseReopened
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public PeriodClose $periodClose,
        public User $user,
        public array $reopenData
    ) {}

    /**
     * Get the subject of the event.
     */
    public function getSubject(): string
    {
        return $this->periodClose->accountingPeriod->name ?? 'Period';
    }

    /**
     * Get the message for the notification.
     */
    public function getMessage(): string
    {
        return "Period '{$this->getSubject()}' has been reopened by {$this->user->name}";
    }

    /**
     * Get the event context for logging.
     */
    public function getContext(): array
    {
        return [
            'period_close_id' => $this->periodClose->id,
            'accounting_period_id' => $this->periodClose->accounting_period_id,
            'user_id' => $this->user->id,
            'company_id' => $this->periodClose->company_id,
            'reopen_reason' => $this->reopenData['reason'],
            'reopen_until' => $this->reopenData['reopen_until'],
            'reopened_at' => $this->periodClose->reopened_at,
        ];
    }
}
