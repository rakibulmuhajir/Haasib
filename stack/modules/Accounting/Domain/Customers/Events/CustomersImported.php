<?php

namespace Modules\Accounting\Domain\Customers\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CustomersImported implements ShouldBroadcast
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
        return 'customers.imported';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'import_batch_id' => $this->data['import_batch_id'],
            'company_id' => $this->data['company_id'],
            'source_type' => $this->data['source_type'],
            'total_count' => $this->data['total_count'],
            'imported_count' => $this->data['imported_count'],
            'skipped_count' => $this->data['skipped_count'],
            'error_count' => $this->data['error_count'],
            'status' => $this->data['total_count'] === $this->data['imported_count'] ? 'completed' : 'partial',
            'timestamp' => $this->data['timestamp'],
        ];
    }
}
