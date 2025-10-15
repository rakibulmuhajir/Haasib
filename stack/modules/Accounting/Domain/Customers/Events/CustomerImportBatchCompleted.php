<?php

namespace Modules\Accounting\Domain\Customers\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CustomerImportBatchCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public array $data
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('company.'.$this->data['company_id']),
            new Channel('customer-imports'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'customers.import-batch-completed';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'import_batch_id' => $this->data['import_batch_id'],
            'company_id' => $this->data['company_id'],
            'total_customers' => $this->data['total_customers'],
            'imported_count' => $this->data['imported_count'],
            'skipped_count' => $this->data['skipped_count'],
            'error_count' => $this->data['error_count'],
            'status' => $this->data['error_count'] > 0 ? 'completed_with_errors' : 'completed',
            'timestamp' => $this->data['timestamp'],
        ];
    }
}
