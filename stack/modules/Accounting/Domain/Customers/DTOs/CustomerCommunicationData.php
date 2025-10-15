<?php

namespace Modules\Accounting\Domain\Customers\DTOs;

class CustomerCommunicationData
{
    public function __construct(
        public readonly ?int $contact_id,
        public readonly string $channel,
        public readonly string $direction,
        public readonly ?string $subject,
        public readonly string $body,
        public readonly \DateTime $occurred_at,
        public readonly ?array $attachments,
    ) {}

    /**
     * Create from array data.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            contact_id: $data['contact_id'] ?? null,
            channel: $data['channel'],
            direction: $data['direction'],
            subject: $data['subject'] ?? null,
            body: $data['body'],
            occurred_at: isset($data['occurred_at'])
                ? new \DateTime($data['occurred_at'])
                : now(),
            attachments: $data['attachments'] ?? null,
        );
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'contact_id' => $this->contact_id,
            'channel' => $this->channel,
            'direction' => $this->direction,
            'subject' => $this->subject,
            'body' => $this->body,
            'occurred_at' => $this->occurred_at->format('Y-m-d H:i:s'),
            'attachments' => $this->attachments,
        ];
    }
}
