<?php

namespace Modules\Ledger\Events;

use App\Models\BankReconciliationAdjustment;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BankReconciliationAdjustmentCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public BankReconciliationAdjustment $adjustment,
        public User $user,
        public array $metadata = []
    ) {
        $this->metadata = array_merge($metadata, [
            'timestamp' => now()->toISOString(),
            'company_id' => $adjustment->reconciliation->company_id,
            'reconciliation_id' => $adjustment->reconciliation_id,
            'adjustment_type_display' => $adjustment->type_display_name,
            'adjustment_icon' => $adjustment->type_icon,
            'adjustment_color' => $adjustment->type_color,
        ]);
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn()
    {
        return [
            new PrivateChannel('company.'.$this->adjustment->reconciliation->company_id.'.bank-reconciliation'),
            new PrivateChannel('bank-reconciliation.'.$this->adjustment->reconciliation_id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'bank.reconciliation.adjustment.created';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'adjustment' => [
                'id' => $this->adjustment->id,
                'reconciliation_id' => $this->adjustment->reconciliation_id,
                'adjustment_type' => $this->adjustment->adjustment_type,
                'amount' => $this->adjustment->signed_amount,
                'description' => $this->adjustment->description,
                'created_at' => $this->adjustment->created_at->toISOString(),
                'type_display_name' => $this->adjustment->type_display_name,
                'type_icon' => $this->adjustment->type_icon,
                'type_color' => $this->adjustment->type_color,
                'journal_entry_id' => $this->adjustment->journal_entry_id,
            ],
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
            'metadata' => $this->metadata,
            'impact' => [
                'variance_change' => $this->adjustment->amount,
                'new_variance' => $this->adjustment->reconciliation->fresh()->formatted_variance,
                'variance_status_change' => $this->adjustment->reconciliation->fresh()->variance_status,
                'can_now_complete' => $this->adjustment->reconciliation->fresh()->canBeCompleted(),
            ],
            'timestamp' => now()->toISOString(),
        ];
    }
}
