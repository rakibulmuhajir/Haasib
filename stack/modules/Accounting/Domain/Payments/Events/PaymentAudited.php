<?php

namespace Modules\Accounting\Domain\Payments\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentAudited implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public array $data
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('company.' . $this->data['company_id'] . '.payments'),
            new PrivateChannel('user.' . $this->data['actor_id'] . '.payments'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'payment.audited';
    }
}