<?php

namespace Modules\Accounting\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Allocation Strategy Service - Handles payment allocation strategies
 */
class AllocationStrategyService
{
    /**
     * Allocate payment using specified strategy
     */
    public function allocate(Payment $payment, string $strategy, User $user, array $options = []): array
    {
        $method = match ($strategy) {
            'fifo' => 'allocateFIFO',
            'lifo' => 'allocateLIFO',
            'proportional' => 'allocateProportional',
            'overdue_first' => 'allocateOverdueFirst',
            'largest_first' => 'allocateLargestFirst',
            'smallest_first' => 'allocateSmallestFirst',
            default => throw new \InvalidArgumentException("Unknown allocation strategy: {$strategy}")
        };

        return $this->$method($payment, $user, $options);
    }

    /**
     * Get available strategies
     */
    public function getStrategies(): array
    {
        return [
            'fifo' => [
                'name' => 'First In, First Out (FIFO)',
                'description' => 'Allocate to oldest invoices first',
                'best_for' => 'Standard accounting practice',
            ],
            'lifo' => [
                'name' => 'Last In, First Out (LIFO)',
                'description' => 'Allocate to newest invoices first',
                'best_for' => 'Recent invoice prioritization',
            ],
            'proportional' => [
                'name' => 'Proportional',
                'description' => 'Allocate proportionally across all invoices',
                'best_for' => 'Fair distribution across multiple invoices',
            ],
            'overdue_first' => [
                'name' => 'Overdue First',
                'description' => 'Prioritize overdue invoices',
                'best_for' => 'Cash flow optimization',
            ],
            'largest_first' => [
                'name' => 'Largest First',
                'description' => 'Allocate to largest balances first',
                'best_for' => 'Risk reduction',
            ],
            'smallest_first' => [
                'name' => 'Smallest First',
                'description' => 'Clear small balances first',
                'best_for' => 'Administrative efficiency',
            ],
        ];
    }

    /**
     * FIFO (First In, First Out) allocation
     */
    protected function allocateFIFO(Payment $payment, User $user, array $options = []): array
    {
        $invoices = $this->getUnpaidInvoices($payment)
            ->orderBy('created_at', 'asc')
            ->get();

        return $this->performAllocation($payment, $invoices, 'fifo', $user, $options);
    }

    /**
     * LIFO (Last In, First Out) allocation
     */
    protected function allocateLIFO(Payment $payment, User $user, array $options = []): array
    {
        $invoices = $this->getUnpaidInvoices($payment)
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->performAllocation($payment, $invoices, 'lifo', $user, $options);
    }

    /**
     * Proportional allocation
     */
    protected function allocateProportional(Payment $payment, User $user, array $options = []): array
    {
        $invoices = $this->getUnpaidInvoices($payment)->get();

        if ($invoices->isEmpty()) {
            return [];
        }

        $totalBalance = $invoices->sum('balance_due');
        $remainingAmount = $payment->remaining_amount;

        $allocations = [];
        foreach ($invoices as $invoice) {
            if ($remainingAmount <= 0) {
                break;
            }

            $proportionalAmount = ($invoice->balance_due / $totalBalance) * $payment->remaining_amount;
            $allocationAmount = min($proportionalAmount, $invoice->balance_due, $remainingAmount);

            if ($allocationAmount > 0.01) { // Only allocate meaningful amounts
                $allocations[] = [
                    'invoice_id' => $invoice->id,
                    'amount' => round($allocationAmount, 2),
                    'notes' => 'Proportional allocation',
                ];
                $remainingAmount -= $allocationAmount;
            }
        }

        return $this->executeAllocations($payment, $allocations, 'proportional', $user);
    }

    /**
     * Overdue First allocation
     */
    protected function allocateOverdueFirst(Payment $payment, User $user, array $options = []): array
    {
        $invoices = $this->getUnpaidInvoices($payment)
            ->orderByRaw('CASE WHEN due_date < NOW() THEN 0 ELSE 1 END, due_date ASC')
            ->get();

        return $this->performAllocation($payment, $invoices, 'overdue_first', $user, $options);
    }

    /**
     * Largest First allocation
     */
    protected function allocateLargestFirst(Payment $payment, User $user, array $options = []): array
    {
        $invoices = $this->getUnpaidInvoices($payment)
            ->orderBy('balance_due', 'desc')
            ->get();

        return $this->performAllocation($payment, $invoices, 'largest_first', $user, $options);
    }

    /**
     * Smallest First allocation
     */
    protected function allocateSmallestFirst(Payment $payment, User $user, array $options = []): array
    {
        $invoices = $this->getUnpaidInvoices($payment)
            ->orderBy('balance_due', 'asc')
            ->get();

        return $this->performAllocation($payment, $invoices, 'smallest_first', $user, $options);
    }

    /**
     * Perform allocation for a set of invoices
     */
    protected function performAllocation(
        Payment $payment,
        Collection $invoices,
        string $strategy,
        User $user,
        array $options = []
    ): array {
        $remainingAmount = $payment->remaining_amount;
        $allocations = [];

        foreach ($invoices as $invoice) {
            if ($remainingAmount <= 0.01) {
                break;
            }

            $allocationAmount = min($invoice->balance_due, $remainingAmount);

            if ($allocationAmount > 0.01) {
                $allocations[] = [
                    'invoice_id' => $invoice->id,
                    'amount' => round($allocationAmount, 2),
                    'notes' => ucfirst($strategy) . ' allocation',
                ];
                $remainingAmount -= $allocationAmount;
            }
        }

        return $this->executeAllocations($payment, $allocations, $strategy, $user);
    }

    /**
     * Execute allocations and create allocation records
     */
    protected function executeAllocations(
        Payment $payment,
        array $allocations,
        string $strategy,
        User $user
    ): array {
        $results = [];

        foreach ($allocations as $allocation) {
            $invoice = Invoice::findOrFail($allocation['invoice_id']);

            // Create allocation record
            $paymentAllocation = \App\Models\PaymentAllocation::create([
                'company_id' => $payment->company_id,
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'allocated_amount' => $allocation['amount'],
                'allocation_date' => now(),
                'allocation_method' => 'automatic',
                'allocation_strategy' => $strategy,
                'notes' => $allocation['notes'] ?? null,
                'created_by_user_id' => $user->id,
            ]);

            // Update invoice
            $invoice->paid_amount += $allocation['amount'];
            $invoice->balance_due = max(0, $invoice->total_amount - $invoice->paid_amount);

            if ($invoice->balance_due <= 0.001) {
                $invoice->status = 'paid';
            } elseif ($invoice->paid_amount > 0) {
                $invoice->status = 'partial';
            }

            $invoice->save();

            $results[] = [
                'allocation_id' => $paymentAllocation->id,
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'allocated_amount' => $allocation['amount'],
            ];
        }

        // Update payment status if fully allocated
        if ($payment->fresh()->remaining_amount <= 0.01) {
            $payment->status = 'completed';
            $payment->save();
        }

        return $results;
    }

    /**
     * Get unpaid invoices for a payment
     */
    protected function getUnpaidInvoices(Payment $payment): \Illuminate\Database\Eloquent\Builder
    {
        return Invoice::where('company_id', $payment->company_id)
            ->where('customer_id', $payment->customer_id)
            ->where('status', '!=', 'paid')
            ->where('status', '!=', 'cancelled')
            ->where('balance_due', '>', 0);
    }
}