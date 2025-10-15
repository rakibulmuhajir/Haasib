<?php

namespace Modules\Accounting\Domain\Customers\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Accounting\Domain\Customers\Models\Customer;
use Modules\Accounting\Domain\Customers\Models\CustomerStatement;

class StatementGenerated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public CustomerStatement $statement,
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
        return 'customer.statement.generated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'statement_id' => $this->statement->id,
            'customer_id' => $this->customer->id,
            'customer_name' => $this->customer->name,
            'period_start' => $this->statement->period_start->format('Y-m-d'),
            'period_end' => $this->statement->period_end->format('Y-m-d'),
            'closing_balance' => $this->statement->closing_balance,
            'generated_at' => $this->statement->generated_at->toISOString(),
            'generated_by' => $this->options['generated_by_user_id'] ?? null,
            'format' => $this->options['format'] ?? 'pdf',
        ];
    }
}
