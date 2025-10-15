<?php

namespace Modules\Accounting\Domain\Payments\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Modules\Accounting\Domain\Payments\Events\PaymentAudited;
use Modules\Accounting\Domain\Payments\Events\AllocationReversed;
use Modules\Accounting\Domain\Payments\Telemetry\PaymentMetrics;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentReversal;
use App\Models\Invoice;

class ReversePaymentAction
{
    /**
     * Execute payment reversal.
     */
    public function execute(string $paymentId, array $data): array
    {
        // Validate input data
        $validated = Validator::make(array_merge($data, ['payment_id' => $paymentId]), [
            'payment_id' => 'required|uuid|exists:invoicing.payments,id',
            'reason' => 'required|string|max:500',
            'amount' => 'nullable|numeric|min:0.01',
            'method' => 'required|string|in:void,refund,chargeback',
            'metadata' => 'nullable|array',
        ])->validate();

        return DB::transaction(function () use ($validated, $paymentId) {
            // Find the payment
            $payment = Payment::findOrFail($paymentId);

            // Check if payment can be reversed
            if (!$payment->canBeReversed()) {
                throw new \InvalidArgumentException(
                    'Payment cannot be reversed. Current status: ' . $payment->status
                );
            }

            // Check if payment is already reversed
            if ($payment->reversal()->exists()) {
                throw new \InvalidArgumentException('Payment has already been reversed');
            }

            // Validate reversal amount
            $reversalAmount = $validated['amount'] ?? $payment->amount;
            if ($reversalAmount > $payment->amount) {
                throw new \InvalidArgumentException(
                    'Reversal amount cannot exceed original payment amount'
                );
            }

            // Create reversal record
            $reversal = PaymentReversal::create([
                'id' => Str::uuid(),
                'payment_id' => $payment->id,
                'company_id' => $payment->company_id,
                'reason' => $validated['reason'],
                'reversed_amount' => $reversalAmount,
                'reversal_method' => $validated['method'],
                'initiated_by_user_id' => auth()->id(),
                'initiated_at' => now(),
                'status' => PaymentReversal::STATUS_PENDING,
                'metadata' => $validated['metadata'] ?? [],
            ]);

            // Reverse all associated allocations
            $this->reverseAssociatedAllocations($payment, $validated['reason']);

            // Update payment status
            $payment->update(['status' => Payment::STATUS_REVERSED]);

            // Record metrics
            PaymentMetrics::paymentReversed(
                $payment->company_id,
                $validated['method'],
                $reversalAmount
            );

            // Emit audit event
            event(new PaymentAudited([
                'payment_id' => $payment->id,
                'company_id' => $payment->company_id,
                'actor_id' => auth()->id(),
                'actor_type' => 'user',
                'action' => 'payment_reversed',
                'timestamp' => now()->toISOString(),
                'metadata' => [
                    'reversal_id' => $reversal->id,
                    'reason' => $validated['reason'],
                    'amount' => $reversalAmount,
                    'method' => $validated['method'],
                    'original_amount' => $payment->amount,
                    'payment_method' => $payment->payment_method,
                    'entity_id' => $payment->customer_id,
                    'metadata' => $validated['metadata'] ?? [],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'allocations_reversed' => $payment->allAllocations()->count(),
                ],
            ]));

            return [
                'payment_id' => $payment->id,
                'reversal_id' => $reversal->id,
                'status' => $reversal->status,
                'message' => 'Payment reversal initiated successfully',
                'reversed_amount' => $reversalAmount,
                'original_amount' => $payment->amount,
                'remaining_amount' => $payment->amount - $reversalAmount,
            ];
        });
    }

    /**
     * Reverse all allocations associated with a payment.
     */
    private function reverseAssociatedAllocations(Payment $payment, string $reason): void
    {
        $allocations = $payment->allocations;
        
        foreach ($allocations as $allocation) {
            // Restore invoice balance
            $this->restoreInvoiceBalance($allocation);
            
            // Mark allocation as reversed
            $allocation->reverse($reason);
            
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
                    'reason' => $reason . ' (automated due to payment reversal)',
                    'original_amount' => $allocation->allocated_amount,
                    'invoice_id' => $allocation->invoice_id,
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ],
            ]));
        }
    }

    /**
     * Restore invoice balance after allocation reversal.
     */
    private function restoreInvoiceBalance(PaymentAllocation $allocation): void
    {
        $invoice = Invoice::find($allocation->invoice_id);
        
        if ($invoice) {
            // Add back the allocated amount to the invoice balance
            $invoice->increment('balance_due', $allocation->effective_amount);
            
            // Update invoice status if needed
            if ($invoice->balance_due > 0) {
                $invoice->update(['status' => 'open']);
            }
        }
    }
}