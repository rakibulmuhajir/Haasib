<?php

namespace Modules\Accounting\Domain\Payments\Events;

class PaymentCreated
{
    public function __construct(
        public array $paymentData
    ) {}
}