<?php

namespace App\Services;

use App\Models\PaymentAllocation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentAllocationReversalService
{
    /**
     * Reverse multiple allocations in a batch.
     */
    public function reverseMultipleAllocations(
        array $allocationIds,
        string $reason,
        User $user
    ): array {
        return DB::transaction(function () use ($allocationIds, $reason, $user) {
            $results = [];
            $reversalCount = 0;
            $errorCount = 0;

            foreach ($allocationIds as $allocationId) {
                try {
                    $allocation = PaymentAllocation::findOrFail($allocationId);
                    
                    if (!$allocation->is_active) {
                        $results[] = [
                            'allocation_id' => $allocationId,
                            'status' => 'already_reversed',
                            'message' => 'Allocation was already reversed',
                        ];
                        $errorCount++;
                        continue;
                    }

                    $this->reverseSingleAllocation($allocation, $reason, $user);
                    
                    $results[] = [
                        'allocation_id' => $allocationId,
                        'status' => 'reversed',
                        'message' => 'Allocation successfully reversed',
                        'reversed_amount' => $allocation->allocated_amount,
                    ];
                    $reversalCount++;

                } catch (\Exception $e) {
                    $results[] = [
                        'allocation_id' => $allocationId,
                        'status' => 'error',
                        'message' => $e->getMessage(),
                    ];
                    $errorCount++;
                }
            }

            Log::info('Batch payment allocation reversal completed', [
                'total_allocations' => count($allocationIds),
                'reversed_count' => $reversalCount,
                'error_count' => $errorCount,
                'user_id' => $user->id,
                'reason' => $reason,
            ]);

            return [
                'total_processed' => count($allocationIds),
                'reversed_count' => $reversalCount,
                'error_count' => $errorCount,
                'results' => $results,
            ];
        });
    }

    /**
     * Reverse all allocations for a specific payment.
     */
    public function reverseAllPaymentAllocations(
        string $paymentId,
        string $reason,
        User $user
    ): array {
        return DB::transaction(function () use ($paymentId, $reason, $user) {
            $allocations = PaymentAllocation::where('payment_id', $paymentId)
                ->active()
                ->get();

            if ($allocations->isEmpty()) {
                return [
                    'message' => 'No active allocations found for this payment',
                    'reversed_count' => 0,
                ];
            }

            $allocationIds = $allocations->pluck('id')->toArray();
            return $this->reverseMultipleAllocations($allocationIds, $reason, $user);
        });
    }

    /**
     * Reverse allocations for a specific invoice.
     */
    public function reverseInvoiceAllocations(
        string $invoiceId,
        string $reason,
        User $user,
        ?float $maxAmount = null
    ): array {
        return DB::transaction(function () use ($invoiceId, $reason, $user, $maxAmount) {
            $query = PaymentAllocation::where('invoice_id', $invoiceId)
                ->active()
                ->orderBy('created_at', 'desc'); // Reverse newest first

            $allocations = $maxAmount 
                ? $query->get()->takeWhile(function ($allocation) use (&$total, $maxAmount) {
                    $total = ($total ?? 0) + $allocation->allocated_amount;
                    return $total <= $maxAmount;
                })
                : $query->get();

            if ($allocations->isEmpty()) {
                return [
                    'message' => 'No active allocations found for this invoice',
                    'reversed_count' => 0,
                ];
            }

            $allocationIds = $allocations->pluck('id')->toArray();
            $result = $this->reverseMultipleAllocations($allocationIds, $reason, $user);
            
            $result['max_amount_limit'] = $maxAmount;
            return $result;
        });
    }

    /**
     * Validate reversal before executing.
     */
    public function validateReversal(array $allocationIds, User $user): array
    {
        $validationResults = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'allocations' => [],
        ];

        foreach ($allocationIds as $allocationId) {
            try {
                $allocation = PaymentAllocation::findOrFail($allocationId);
                
                // Check if allocation is already reversed
                if (!$allocation->is_active) {
                    $validationResults['warnings'][] = [
                        'allocation_id' => $allocationId,
                        'warning' => 'Allocation is already reversed',
                    ];
                    continue;
                }

                // Check user permissions
                if (!$this->canUserReverseAllocation($user, $allocation)) {
                    $validationResults['valid'] = false;
                    $validationResults['errors'][] = [
                        'allocation_id' => $allocationId,
                        'error' => 'User does not have permission to reverse this allocation',
                    ];
                    continue;
                }

                // Check if allocation can be reversed (business rules)
                $canReverse = $this->canReverseAllocation($allocation);
                if (!$canReverse['allowed']) {
                    $validationResults['valid'] = false;
                    $validationResults['errors'][] = [
                        'allocation_id' => $allocationId,
                        'error' => $canReverse['reason'],
                    ];
                    continue;
                }

                $validationResults['allocations'][] = [
                    'allocation_id' => $allocationId,
                    'allocated_amount' => $allocation->allocated_amount,
                    'allocation_date' => $allocation->allocation_date->toISOString(),
                    'invoice_number' => $allocation->invoice->invoice_number,
                    'payment_number' => $allocation->payment->payment_number,
                ];

            } catch (\Exception $e) {
                $validationResults['valid'] = false;
                $validationResults['errors'][] = [
                    'allocation_id' => $allocationId,
                    'error' => 'Allocation not found: ' . $e->getMessage(),
                ];
            }
        }

        return $validationResults;
    }

    /**
     * Get reversal history for allocations.
     */
    public function getReversalHistory(array $allocationIds): array
    {
        $allocations = PaymentAllocation::with(['reverser', 'invoice', 'payment'])
            ->whereIn('id', $allocationIds)
            ->whereNotNull('reversed_at')
            ->orderBy('reversed_at', 'desc')
            ->get();

        return $allocations->map(function ($allocation) {
            return [
                'allocation_id' => $allocation->id,
                'reversed_at' => $allocation->reversed_at->toISOString(),
                'reversal_reason' => $allocation->reversal_reason,
                'reversed_by' => $allocation->reverser ? [
                    'id' => $allocation->reverser->id,
                    'name' => $allocation->reverser->name,
                ] : null,
                'original_allocation' => [
                    'allocated_amount' => $allocation->allocated_amount,
                    'allocation_date' => $allocation->allocation_date->toISOString(),
                    'invoice_number' => $allocation->invoice->invoice_number,
                    'payment_number' => $allocation->payment->payment_number,
                ],
            ];
        })->toArray();
    }

    /**
     * Get potential reversal impact analysis.
     */
    public function getReversalImpact(array $allocationIds): array
    {
        $allocations = PaymentAllocation::with(['invoice', 'payment'])
            ->whereIn('id', $allocationIds)
            ->active()
            ->get();

        if ($allocations->isEmpty()) {
            return [
                'total_reversal_amount' => 0,
                'affected_invoices' => 0,
                'affected_payments' => 0,
                'invoice_impacts' => [],
                'payment_impacts' => [],
            ];
        }

        $totalReversalAmount = $allocations->sum('allocated_amount');
        $affectedInvoices = $allocations->pluck('invoice_id')->unique()->count();
        $affectedPayments = $allocations->pluck('payment_id')->unique()->count();

        // Group by invoices to show impact
        $invoiceImpacts = $allocations->groupBy('invoice_id')
            ->map(function ($invoiceAllocations, $invoiceId) {
                $invoice = $invoiceAllocations->first()->invoice;
                $totalAmount = $invoiceAllocations->sum('allocated_amount');
                
                return [
                    'invoice_id' => $invoiceId,
                    'invoice_number' => $invoice->invoice_number,
                    'current_balance_due' => $invoice->balance_due,
                    'current_payment_status' => $invoice->payment_status,
                    'reversal_amount' => $totalAmount,
                    'projected_balance_due' => $invoice->balance_due + $totalAmount,
                    'projected_payment_status' => $this->calculateProjectedPaymentStatus($invoice, $totalAmount),
                ];
            })->values()->toArray();

        // Group by payments to show impact
        $paymentImpacts = $allocations->groupBy('payment_id')
            ->map(function ($paymentAllocations, $paymentId) {
                $payment = $paymentAllocations->first()->payment;
                $totalAmount = $paymentAllocations->sum('allocated_amount');
                
                return [
                    'payment_id' => $paymentId,
                    'payment_number' => $payment->payment_number,
                    'current_total_allocated' => $payment->total_allocated,
                    'current_remaining_amount' => $payment->remaining_amount,
                    'reversal_amount' => $totalAmount,
                    'projected_total_allocated' => $payment->total_allocated - $totalAmount,
                    'projected_remaining_amount' => $payment->remaining_amount + $totalAmount,
                ];
            })->values()->toArray();

        return [
            'total_reversal_amount' => $totalReversalAmount,
            'affected_invoices' => $affectedInvoices,
            'affected_payments' => $affectedPayments,
            'invoice_impacts' => $invoiceImpacts,
            'payment_impacts' => $paymentImpacts,
        ];
    }

    /**
     * Reverse a single allocation.
     */
    private function reverseSingleAllocation(
        PaymentAllocation $allocation,
        string $reason,
        User $user
    ): void {
        $allocation->reverse($reason, $user);

        // Update payment status if no longer fully allocated
        $payment = $allocation->payment;
        if (!$payment->is_fully_allocated && $payment->status === 'completed') {
            $payment->status = 'pending';
            $payment->save();
        }

        Log::info('Payment allocation reversed', [
            'allocation_id' => $allocation->id,
            'reason' => $reason,
            'user_id' => $user->id,
            'amount' => $allocation->allocated_amount,
        ]);
    }

    /**
     * Check if user can reverse allocation.
     */
    private function canUserReverseAllocation(User $user, PaymentAllocation $allocation): bool
    {
        // Check if user belongs to the same company
        if (!$user->companies()->where('companies.id', $allocation->company_id)->exists()) {
            return false;
        }

        // Check user permissions
        return $user->hasPermissionTo('payment_allocations.reverse') ||
               $user->hasRole(['admin', 'owner', 'super_admin']);
    }

    /**
     * Check if allocation can be reversed based on business rules.
     */
    private function canReverseAllocation(PaymentAllocation $allocation): array
    {
        // Allocation must be active (not already reversed)
        if (!$allocation->is_active) {
            return ['allowed' => false, 'reason' => 'Allocation is already reversed'];
        }

        // Check if allocation is too old (optional business rule)
        $daysSinceAllocation = $allocation->allocation_date->diffInDays(now());
        if ($daysSinceAllocation > 365) {
            return ['allowed' => false, 'reason' => 'Allocation is too old to reverse (over 1 year)'];
        }

        // Check if invoice is still active
        if ($allocation->invoice->status === 'cancelled') {
            return ['allowed' => false, 'reason' => 'Associated invoice is cancelled'];
        }

        return ['allowed' => true];
    }

    /**
     * Calculate projected payment status after reversal.
     */
    private function calculateProjectedPaymentStatus(Invoice $invoice, float $reversalAmount): string
    {
        $projectedBalance = $invoice->balance_due + $reversalAmount;
        $currentAllocated = $invoice->total_allocated;
        $projectedAllocated = $currentAllocated - $reversalAmount;

        if ($projectedAllocated <= 0) {
            return 'unpaid';
        } elseif ($projectedBalance > 0) {
            return 'partially_paid';
        } else {
            return 'paid';
        }
    }
}