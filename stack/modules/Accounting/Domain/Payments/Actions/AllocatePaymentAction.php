<?php

namespace Modules\Accounting\Domain\Payments\Actions;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Modules\Accounting\Domain\Payments\Events\EarlyPaymentDiscountApplied;
use Modules\Accounting\Domain\Payments\Events\PaymentAllocated;
use Modules\Accounting\Domain\Payments\Events\PaymentAudited;
use Modules\Accounting\Domain\Payments\Events\UnallocatedCashCreated;
use Modules\Accounting\Domain\Payments\Telemetry\PaymentMetrics;

class AllocatePaymentAction
{
    /**
     * Execute manual payment allocation.
     */
    public function execute(string $paymentId, array $allocations): array
    {
        // Validate input data
        $validated = Validator::make([
            'payment_id' => $paymentId,
            'allocations' => $allocations,
        ], [
            'payment_id' => 'required|uuid|exists:pgsql.acct.payments,payment_id',
            'allocations' => 'required|array|min:1',
            'allocations.*.invoice_id' => 'required|uuid|exists:pgsql.acct.invoices,invoice_id',
            'allocations.*.amount' => 'required|numeric|min:0.01',
            'allocations.*.apply_early_payment_discount' => 'nullable|boolean',
            'allocations.*.notes' => 'nullable|string',
        ])->validate();

        return DB::transaction(function () use ($paymentId, $validated) {
            $payment = Payment::findOrFail($paymentId);

            // Check payment status
            if ($payment->status === 'completed') {
                throw new \InvalidArgumentException('Payment is already fully allocated');
            }

            if ($payment->status !== 'pending') {
                throw new \InvalidArgumentException('Cannot allocate payment with status: '.$payment->status);
            }

            // Calculate total allocation amount
            $totalAllocationAmount = array_sum(array_column($validated['allocations'], 'amount'));

            // Check for over-allocation
            $currentAllocated = $payment->allocations()->sum('allocated_amount') ?? 0;
            $remainingAmount = $payment->amount - $currentAllocated;

            if ($totalAllocationAmount > $remainingAmount) {
                throw new \InvalidArgumentException(sprintf(
                    'Allocation amount (%.2f) exceeds remaining payment amount (%.2f)',
                    $totalAllocationAmount,
                    $remainingAmount
                ));
            }

            $createdAllocations = [];
            $totalDiscountAmount = 0;

            // Create allocation records
            foreach ($validated['allocations'] as $allocationData) {
                $discountAmount = 0;
                $discountPercent = 0;
                $finalAllocatedAmount = $allocationData['amount'];

                // Check if early payment discount should be applied
                if (! empty($allocationData['apply_early_payment_discount'])) {
                    $invoice = Invoice::findOrFail($allocationData['invoice_id']);
                    $discountInfo = $this->calculateEarlyPaymentDiscount($invoice, $allocationData['amount']);

                    if ($discountInfo['eligible']) {
                        $discountAmount = $discountInfo['discount_amount'];
                        $discountPercent = $discountInfo['discount_percent'];
                        $finalAllocatedAmount = $discountInfo['final_amount'];
                        $totalDiscountAmount += $discountAmount;

                        // Update invoice balance
                        $invoice->balance_due -= $finalAllocatedAmount;
                        $invoice->save();

                        // Fire discount applied event
                        EarlyPaymentDiscountApplied::dispatch([
                            'payment_id' => $paymentId,
                            'invoice_id' => $allocationData['invoice_id'],
                            'discount_amount' => $discountAmount,
                            'discount_percent' => $discountPercent,
                            'original_amount' => $allocationData['amount'],
                            'final_amount' => $finalAllocatedAmount,
                        ]);
                    }
                }

                $allocation = PaymentAllocation::create([
                    'allocation_id' => Str::uuid(),
                    'payment_id' => $paymentId,
                    'invoice_id' => $allocationData['invoice_id'],
                    'allocated_amount' => $finalAllocatedAmount,
                    'original_amount' => $allocationData['amount'],
                    'discount_amount' => $discountAmount,
                    'discount_percent' => $discountPercent,
                    'status' => 'active',
                    'allocation_date' => now(),
                    'allocation_method' => 'manual',
                    'notes' => $allocationData['notes'] ?? null,
                    'created_by_user_id' => auth()->id(),
                ]);

                $createdAllocations[] = [
                    'allocation_id' => $allocation->allocation_id,
                    'invoice_id' => $allocation->invoice_id,
                    'allocated_amount' => $allocation->allocated_amount,
                    'original_amount' => $allocation->original_amount,
                    'discount_amount' => $allocation->discount_amount,
                    'discount_percent' => $allocation->discount_percent,
                    'notes' => $allocation->notes,
                ];
            }

            // Update payment status if fully allocated
            $newTotalAllocated = $currentAllocated + array_sum(array_column($createdAllocations, 'allocated_amount'));
            $newRemainingAmount = $payment->amount - $newTotalAllocated;

            if ($newRemainingAmount <= 0) {
                $payment->status = 'completed';
                $payment->save();
            }

            // Create unallocated cash entry if there's remaining amount
            $unallocatedCashCreated = false;
            if ($newRemainingAmount > 0) {
                $this->createUnallocatedCashEntry($payment, $newRemainingAmount);
                $unallocatedCashCreated = true;
            }

            // Record metrics
            PaymentMetrics::allocationApplied(
                $payment->company_id,
                'manual',
                count($createdAllocations),
                $newTotalAllocated
            );

            // Fire payment allocated event
            PaymentAllocated::dispatch([
                'payment_id' => $paymentId,
                'allocations_count' => count($createdAllocations),
                'total_allocated' => $newTotalAllocated,
                'remaining_amount' => $newRemainingAmount,
                'total_discount_applied' => $totalDiscountAmount,
                'unallocated_cash_created' => $unallocatedCashCreated,
            ]);

            // Emit audit event for allocation
            event(new PaymentAudited([
                'payment_id' => $paymentId,
                'company_id' => $payment->company_id,
                'actor_id' => auth()->id(),
                'actor_type' => 'user',
                'action' => 'payment_allocated',
                'timestamp' => now()->toISOString(),
                'metadata' => [
                    'allocations_count' => count($createdAllocations),
                    'total_allocated' => $newTotalAllocated,
                    'remaining_amount' => $newRemainingAmount,
                    'total_discount_applied' => $totalDiscountAmount,
                    'unallocated_cash_created' => $unallocatedCashCreated,
                    'payment_status' => $payment->status,
                    'allocation_method' => 'manual',
                    'allocations' => $createdAllocations,
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ],
            ]));

            return [
                'payment_id' => $paymentId,
                'allocations_created' => count($createdAllocations),
                'total_allocated' => $newTotalAllocated,
                'total_discount_applied' => $totalDiscountAmount,
                'remaining_amount' => max(0, $newRemainingAmount),
                'payment_status' => $payment->status,
                'is_fully_allocated' => $newRemainingAmount <= 0,
                'unallocated_cash_created' => $unallocatedCashCreated,
                'allocations' => $createdAllocations,
                'message' => 'Payment allocated successfully',
            ];
        });
    }

    /**
     * Calculate early payment discount for an invoice.
     */
    private function calculateEarlyPaymentDiscount(Invoice $invoice, float $paymentAmount): array
    {
        // Check if invoice has early payment discount terms
        if (! $invoice->early_payment_discount_percent || ! $invoice->early_payment_discount_days) {
            return [
                'eligible' => false,
                'reason' => 'No early payment discount terms',
            ];
        }

        // Check if payment is within discount period
        $dueDate = \Carbon\Carbon::parse($invoice->due_date);
        $today = \Carbon\Carbon::today();
        $daysUntilDue = $today->diffInDays($dueDate, false);

        if ($daysUntilDue < 0) {
            return [
                'eligible' => false,
                'reason' => 'Invoice is overdue',
            ];
        }

        if ($daysUntilDue > $invoice->early_payment_discount_days) {
            return [
                'eligible' => false,
                'reason' => 'Payment is outside discount period',
            ];
        }

        // Calculate discount
        $discountAmount = $paymentAmount * ($invoice->early_payment_discount_percent / 100);
        $finalAmount = $paymentAmount - $discountAmount;

        return [
            'eligible' => true,
            'discount_amount' => round($discountAmount, 2),
            'discount_percent' => $invoice->early_payment_discount_percent,
            'original_amount' => $paymentAmount,
            'final_amount' => round($finalAmount, 2),
            'days_until_due' => $daysUntilDue,
        ];
    }

    /**
     * Create unallocated cash entry for overpayment.
     */
    private function createUnallocatedCashEntry(Payment $payment, float $amount): void
    {
        // This would typically create a record in an unallocated_cash table
        // For now, we'll log it and fire an event
        UnallocatedCashCreated::dispatch([
            'payment_id' => $payment->id,
            'customer_id' => $payment->customer_id,
            'company_id' => $payment->company_id,
            'amount' => $amount,
            'currency' => $payment->currency,
            'payment_date' => $payment->payment_date,
            'notes' => 'Unallocated cash from payment overage',
        ]);

        // Create unallocated cash record for the overage amount
        \App\Models\UnallocatedCash::createFromPayment($payment, $amount);

        // Log the unallocated cash creation
        Log::info('Unallocated cash created from payment overage', [
            'payment_id' => $payment->id,
            'customer_id' => $payment->customer_id,
            'amount' => $amount,
            'currency' => $payment->currency,
            'payment_number' => $payment->payment_number,
        ]);
    }
}
