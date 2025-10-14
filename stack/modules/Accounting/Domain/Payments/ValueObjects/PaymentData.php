<?php

namespace Modules\Accounting\Domain\Payments\ValueObjects;

class PaymentData
{
    public function __construct(
        public string $customerId,
        public string $paymentMethod,
        public float $amount,
        public string $currency,
        public string $paymentDate,
        public ?string $referenceNumber = null,
        public ?string $notes = null,
        public bool $autoAllocate = false,
        public ?string $allocationStrategy = null,
        public array $allocationOptions = []
    ) {}
}