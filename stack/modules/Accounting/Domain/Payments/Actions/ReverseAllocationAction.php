<?php

namespace Modules\Accounting\Domain\Payments\Actions;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Domain\Payments\Events\AllocationReversed;
use Modules\Accounting\Domain\Payments\Telemetry\PaymentMetrics;
use App\Models\PaymentAllocation;
use App\Models\Invoice;
use App\Models\Payment;

class ReverseAllocationAction
{
    /**
     * Execute allocation reversal.
     */
    public function execute(string $allocationId, array $data): array
    {
        // Validate input data
        $validated = Validator::make(array_merge($data, ['allocation_id' => $allocationId]), [
            'allocation_id' => 'required|uuid|exists:invoicing.payment_allocations,id',
            'reason' => 'required|string|max:500',
            'refund_amount' => 'nullable|numeric|min:0.01',
        ])->validate();

        return DB::transaction(function () use ($validated, $allocationId) {
            // Find the allocation with its relationships
            $allocation = PaymentAllocation::with(['payment', 'invoice'])
                ->findOrFail($allocationId);

            // Check if allocation can be reversed
            if (!$allocation->canBeReversed()) {
                throw new \InvalidArgumentException(
                    'Allocation cannot be reversed. Status: ' . $allocation->status
                );
            }

            // Validate refund amount
            $refundAmount = $validated['refund_amount'] ?? $allocation->allocated_amount;
            if ($refundAmount > $allocation->allocated_amount) {
                throw new \InvalidArgumentException(
                    'Refund amount cannot exceed allocated amount'
                );
            }

            // Store original state before changes
            $originalAmount = $allocation->allocated_amount;
            $payment = $allocation->payment;
            $invoice = $allocation->invoice;

            // Reverse the allocation
            $allocation->reverse($validated['reason']);

            // Restore invoice balance
            $this->restoreInvoiceBalance($allocation);

            // Update payment status if needed
            $this->updatePaymentStatus($payment);

            // Record metrics
            PaymentMetrics::allocationReversed(
                $payment->company_id,
                $originalAmount,
                $refundAmount
            );

            // Emit allocation reversal audit event
            event(new AllocationReversed([
                'allocation_id' => $allocation->id,
                'payment_id' => $payment->id,
                'company_id' => $payment->company_id,
                'actor_id' => auth()->id(),
                'actor_type' => 'user',
                'action' => 'allocation_reversed',
                'timestamp' => now()->toISOString(),
                'metadata' => [
                    'reason' => $validated['reason'],
                    'refund_amount' => $refundAmount,
                    'original_amount' => $originalAmount,
                    'invoice_id' => $allocation->invoice_id,
                    'invoice_number' => $invoice?->invoice_number,
                    'payment_number' => $payment?->payment_number,
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ],
            ]));

            return [
                'allocation_id' => $allocation->id,
                'status' => 'completed',
                'reversal_id' => $allocation->id,
                'message' => 'Allocation reversal completed successfully',
                'refunded_amount' => $refundAmount,
                'original_amount' => $originalAmount,
                'invoice_balance_restored' => $refundAmount,
            ];
        });
    }

    /**
     * Restore invoice balance after allocation reversal.
     */
    private function restoreInvoiceBalance(PaymentAllocation $allocation): void
    {
        $invoice = $allocation->invoice;
        
        if ($invoice) {
            // Add back the allocated amount to the invoice balance
            $refundAmount = $allocation->effective_amount;
            $invoice->increment('balance_due', $refundAmount);
            
            // Update invoice status if needed
            $this->updateInvoiceStatus($invoice);
        }
    }

    /**
     * Update invoice status based on new balance.
     */
    private function updateInvoiceStatus(Invoice $invoice): void
    {
        if ($invoice->balance_due > 0) {
            $invoice->update(['status' => 'open']);
        } elseif ($invoice->balance_due == 0) {
            $invoice->update(['status' => 'paid']);
        }
    }

    /**
     * Update payment status if all allocations are reversed.
     */
    private function updatePaymentStatus(Payment $payment): void
    {
        // Check if all allocations are reversed
        $activeAllocationsCount = $payment->allocations()->count();
        
        if ($activeAllocationsCount === 0) {
            // All allocations reversed, update payment status
            $payment->update(['status' => Payment::STATUS_PENDING]);
        }
    }
}