<?php

namespace Modules\Ledger\Events;

use App\Models\BankReconciliation;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BankReconciliationStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public BankReconciliation $reconciliation,
        public string $oldStatus,
        public string $newStatus,
        public User $user,
        public ?string $reason = null,
        public array $metadata = []
    ) {
        $this->metadata = array_merge($metadata, [
            'timestamp' => now()->toISOString(),
            'company_id' => $reconciliation->company_id,
            'statement_period' => $reconciliation->statement?->statement_period,
            'bank_account_name' => $reconciliation->ledgerAccount?->name,
            'user_name' => $user->name,
        ]);
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn()
    {
        return [
            new PrivateChannel('company.'.$this->reconciliation->company_id.'.bank-reconciliation'),
            new PrivateChannel('bank-reconciliation.'.$this->reconciliation->id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'bank.reconciliation.status.changed';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'reconciliation_id' => $this->reconciliation->id,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
            'reason' => $this->reason,
            'metadata' => $this->metadata,
            'summary' => [
                'statement_period' => $this->reconciliation->statement?->statement_period,
                'bank_account' => $this->reconciliation->ledgerAccount?->name,
                'variance' => $this->reconciliation->formatted_variance,
                'variance_status' => $this->reconciliation->variance_status,
                'percent_complete' => $this->reconciliation->percent_complete,
                'matches_count' => $this->reconciliation->matches()->count(),
                'adjustments_count' => $this->reconciliation->adjustments()->count(),
            ],
            'permissions' => [
                'can_be_edited' => $this->reconciliation->canBeEdited(),
                'can_be_completed' => $this->reconciliation->canBeCompleted(),
                'can_be_locked' => $this->reconciliation->canBeLocked(),
                'can_be_reopened' => $this->reconciliation->canBeReopened(),
            ],
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Determine if this event should broadcast.
     */
    public function broadcastWhen(): bool
    {
        // Only broadcast if the status actually changed
        return $this->oldStatus !== $this->newStatus;
    }
}
