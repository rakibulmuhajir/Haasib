<?php

namespace Modules\Accounting\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Payment Allocation Service - Handles payment allocation logic
 */
class PaymentAllocationService
{
    protected AllocationStrategyService $strategyService;

    public function __construct(AllocationStrategyService $strategyService)
    {
        $this->strategyService = $strategyService;
    }

    /**
     * Allocate payment across multiple invoices
     */
    public function allocatePaymentAcrossInvoices(
        Payment $payment,
        array $allocations,
        User $user,
        string $method = 'manual',
        ?string $strategy = null
    ): array {
        $results = [];
        $totalToAllocate = 0;

        // Validate total allocation amount
        foreach ($allocations as $allocation) {
            $totalToAllocate += $allocation['amount'];
        }

        if ($totalToAllocate > $payment->remaining_amount) {
            throw new \InvalidArgumentException(
                "Total allocation amount ({$totalToAllocate}) exceeds remaining payment amount ({$payment->remaining_amount})"
            );
        }

        // Process each allocation
        foreach ($allocations as $allocation) {
            $invoice = Invoice::findOrFail($allocation['invoice_id']);
            $amount = $allocation['amount'];

            // Validate invoice belongs to same company
            if ($invoice->company_id !== $payment->company_id) {
                throw new \InvalidArgumentException("Invoice {$invoice->id} does not belong to the same company as payment");
            }

            // Validate allocation amount
            if ($amount <= 0) {
                throw new \InvalidArgumentException("Allocation amount must be greater than 0");
            }

            if ($amount > $invoice->balance_due) {
                throw new \InvalidArgumentException(
                    "Allocation amount ({$amount}) exceeds invoice balance due ({$invoice->balance_due})"
                );
            }

            // Create allocation
            $paymentAllocation = $this->createAllocation(
                $payment,
                $invoice,
                $amount,
                $method,
                $strategy,
                $allocation['notes'] ?? null,
                $user
            );

            $results[] = [
                'allocation_id' => $paymentAllocation->id,
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'allocated_amount' => $amount,
                'previous_balance' => $invoice->balance_due + $amount,
                'new_balance' => $invoice->fresh()->balance_due,
            ];
        }

        return $results;
    }

    /**
     * Apply allocation strategy to a payment
     */
    public function applyAllocationStrategy(
        Payment $payment,
        string $strategy,
        User $user,
        array $options = []
    ): array {
        return $this->strategyService->allocate(
            $payment,
            $strategy,
            $user,
            $options
        );
    }

    /**
     * Get payment allocation summary
     */
    public function getPaymentAllocationSummary(Payment $payment): array
    {
        return $payment->activeAllocations->map(function ($allocation) {
            return [
                'allocation_id' => $allocation->id,
                'invoice_id' => $allocation->invoice_id,
                'invoice_number' => $allocation->invoice->invoice_number,
                'allocated_amount' => $allocation->allocated_amount,
                'allocation_date' => $allocation->allocation_date->format('Y-m-d'),
                'method' => $allocation->allocation_method,
                'notes' => $allocation->notes,
            ];
        })->toArray();
    }

    /**
     * Get available allocation strategies
     */
    public function getAvailableStrategies(): array
    {
        return $this->strategyService->getStrategies();
    }

    /**
     * Create a payment allocation
     */
    protected function createAllocation(
        Payment $payment,
        Invoice $invoice,
        float $amount,
        string $method,
        ?string $strategy = null,
        ?string $notes = null,
        ?User $user = null
    ): PaymentAllocation {
        $allocation = PaymentAllocation::create([
            'company_id' => $payment->company_id,
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'allocated_amount' => $amount,
            'allocation_date' => now(),
            'allocation_method' => $method,
            'allocation_strategy' => $strategy,
            'notes' => $notes,
            'created_by_user_id' => $user?->id,
        ]);

        // Update invoice balance
        $invoice->paid_amount += $amount;
        $invoice->balance_due = max(0, $invoice->total_amount - $invoice->paid_amount);

        // Update invoice status
        if ($invoice->balance_due <= 0.001) {
            $invoice->status = 'paid';
        } elseif ($invoice->paid_amount > 0) {
            $invoice->status = 'partial';
        }

        $invoice->save();

        return $allocation;
    }
}