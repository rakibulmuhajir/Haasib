<?php

namespace Modules\Accounting\Domain\Customers\DTOs;

class CustomerContactData
{
    public function __construct(
        public readonly string $first_name,
        public readonly string $last_name,
        public readonly string $email,
        public readonly ?string $phone,
        public readonly string $role,
        public readonly bool $is_primary,
        public readonly string $preferred_channel,
    ) {}

    /**
     * Create from array data.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            first_name: $data['first_name'],
            last_name: $data['last_name'],
            email: $data['email'],
            phone: $data['phone'] ?? null,
            role: $data['role'],
            is_primary: (bool) ($data['is_primary'] ?? false),
            preferred_channel: $data['preferred_channel'] ?? 'email',
        );
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'is_primary' => $this->is_primary,
            'preferred_channel' => $this->preferred_channel,
        ];
    }
}
