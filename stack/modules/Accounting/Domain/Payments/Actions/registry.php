<?php

return [
    // Payment actions
    'payment.create' => Modules\Accounting\Domain\Payments\Actions\RecordPaymentAction::class,
    'payment.allocate' => Modules\Accounting\Domain\Payments\Actions\AllocatePaymentAction::class,
    'payment.allocate.auto' => Modules\Accounting\Domain\Payments\Actions\AutoAllocatePaymentAction::class,
    'payment.reverse' => Modules\Accounting\Domain\Payments\Actions\ReversePaymentAction::class,
    'payment.allocation.reverse' => Modules\Accounting\Domain\Payments\Actions\ReverseAllocationAction::class,
    
    // Batch actions
    'payment.batch.create' => Modules\Accounting\Domain\Payments\Actions\CreatePaymentBatchAction::class,
    'payment.batch.process' => Modules\Accounting\Domain\Payments\Actions\ProcessPaymentBatchAction::class,
];