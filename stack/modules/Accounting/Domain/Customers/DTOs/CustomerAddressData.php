<?php

namespace Modules\Accounting\Domain\Customers\DTOs;

class CustomerAddressData
{
    public function __construct(
        public readonly string $label,
        public readonly string $type,
        public readonly string $line1,
        public readonly ?string $line2,
        public readonly ?string $city,
        public readonly ?string $state,
        public readonly ?string $postal_code,
        public readonly string $country,
        public readonly bool $is_default,
        public readonly ?string $notes,
    ) {}

    /**
     * Create from array data.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            label: $data['label'],
            type: $data['type'],
            line1: $data['line1'],
            line2: $data['line2'] ?? null,
            city: $data['city'] ?? null,
            state: $data['state'] ?? null,
            postal_code: $data['postal_code'] ?? null,
            country: $data['country'],
            is_default: (bool) ($data['is_default'] ?? false),
            notes: $data['notes'] ?? null,
        );
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'type' => $this->type,
            'line1' => $this->line1,
            'line2' => $this->line2,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postal_code,
            'country' => $this->country,
            'is_default' => $this->is_default,
            'notes' => $this->notes,
        ];
    }
}
