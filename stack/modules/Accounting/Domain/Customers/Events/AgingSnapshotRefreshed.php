<?php

namespace Modules\Accounting\Domain\Customers\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Accounting\Domain\Customers\Models\Customer;
use Modules\Accounting\Domain\Customers\Models\CustomerAgingSnapshot;

class AgingSnapshotRefreshed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public CustomerAgingSnapshot $snapshot,
        public Customer $customer,
        public array $options = []
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('company.'.$this->customer->company_id),
            new PrivateChannel('customer.'.$this->customer->id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'customer.aging.refreshed';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        $totalOutstanding = $this->snapshot->bucket_current + $this->snapshot->bucket_1_30 +
                          $this->snapshot->bucket_31_60 + $this->snapshot->bucket_61_90 + $this->snapshot->bucket_90_plus;

        return [
            'snapshot_id' => $this->snapshot->id,
            'customer_id' => $this->customer->id,
            'customer_name' => $this->customer->name,
            'snapshot_date' => $this->snapshot->snapshot_date->format('Y-m-d'),
            'total_outstanding' => $totalOutstanding,
            'buckets' => [
                'current' => $this->snapshot->bucket_current,
                '1_30' => $this->snapshot->bucket_1_30,
                '31_60' => $this->snapshot->bucket_31_60,
                '61_90' => $this->snapshot->bucket_61_90,
                '90_plus' => $this->snapshot->bucket_90_plus,
            ],
            'total_invoices' => $this->snapshot->total_invoices,
            'generated_via' => $this->snapshot->generated_via,
            'generated_by' => $this->options['generated_by_user_id'] ?? null,
            'created_at' => $this->snapshot->created_at->toISOString(),
        ];
    }
}
