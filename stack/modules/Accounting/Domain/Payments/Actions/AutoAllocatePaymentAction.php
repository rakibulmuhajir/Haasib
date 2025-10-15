<?php

namespace Modules\Accounting\Domain\Payments\Actions;

use Illuminate\Support\Facades\Validator;

class AutoAllocatePaymentAction
{
    /**
     * Execute automatic payment allocation.
     */
    public function execute(string $paymentId, string $strategy, array $options = []): array
    {
        // Validate input data
        $validated = Validator::make([
            'payment_id' => $paymentId,
            'strategy' => $strategy,
            'options' => $options,
        ], [
            'payment_id' => 'required|uuid|exists:payments,id',
            'strategy' => 'required|string|in:fifo,proportional,overdue_first,largest_first,percentage_based,custom_priority',
            'options' => 'nullable|array',
        ])->validate();

        // Find the payment
        $payment = Payment::findOrFail($paymentId);
        
        // Execute auto-allocation based on strategy
        $allocationService = new PaymentAllocationService();
        $result = $allocationService->autoAllocate($payment, $strategy, $options ?? []);
        
        return [
            'payment_id' => $paymentId,
            'strategy_used' => $strategy,
            'allocations_created' => $result['allocations_count'],
            'total_allocated' => $result['total_allocated'],
            'message' => $result['message'],
        ];
    }
}