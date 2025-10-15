<?php

namespace Modules\Accounting\Domain\Customers\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CustomersExported implements ShouldBroadcast
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
            new Channel('customer-exports'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'customers.exported';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'export_batch_id' => $this->data['export_batch_id'],
            'company_id' => $this->data['company_id'],
            'format' => $this->data['format'],
            'total_count' => $this->data['total_count'],
            'exported_count' => $this->data['exported_count'],
            'file_name' => $this->data['file_name'],
            'file_size' => $this->data['file_size'],
            'download_url' => $this->data['download_url'],
            'expires_at' => $this->data['expires_at'],
            'timestamp' => $this->data['timestamp'],
        ];
    }
}
