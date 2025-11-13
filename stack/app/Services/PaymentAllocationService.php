<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Traits\AuditLogging;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentAllocationService
{
    use AuditLogging;

    protected AllocationStrategyService $strategyService;

    private ServiceContext $context;

    public function __construct(
        AllocationStrategyService $strategyService,
        ServiceContext $context
    ) {
        $this->strategyService = $strategyService;
        $this->context = $context;
    }

    /**
     * Allocate payment across multiple invoices.
     */
    public function allocatePaymentAcrossInvoices(
        Payment $payment,
        array $allocations,
        ?User $user = null,
        string $method = 'manual',
        ?string $strategy = null
    ): array {
        return DB::transaction(function () use ($payment, $allocations, $user, $method, $strategy) {
            // Use user from context if not provided
            $actingUser = $user ?? $this->context->getUser();

            // Validate company access
            $this->validateCompanyAccess($payment->company_id);

            // Set RLS context
            $this->setRlsContext($payment->company_id);

            $results = [];
            $totalToAllocate = 0;
            $beforeStates = [];

            // Store before states for audit
            foreach ($allocations as $allocation) {
                $invoice = Invoice::findOrFail($allocation['invoice_id']);
                $beforeStates[$invoice->id] = [
                    'balance_due' => $invoice->balance_due,
                    'total_allocated' => $invoice->total_allocated,
                    'payment_status' => $invoice->payment_status,
                ];
            }

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
                    throw new \InvalidArgumentException('Allocation amount must be greater than 0');
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
                    $actingUser
                );

                $results[] = [
                    'allocation_id' => $paymentAllocation->id,
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'allocated_amount' => $amount,
                    'previous_balance' => $beforeStates[$invoice->id]['balance_due'],
                    'new_balance' => $invoice->fresh()->balance_due,
                ];
            }

            // Update payment status if fully allocated
            if ($payment->fresh()->is_fully_allocated) {
                $previousStatus = $payment->status;
                $payment->status = 'completed';
                $payment->save();

                // Log payment status change
                $this->audit('payment.status_changed', [
                    'payment_id' => $payment->id,
                    'previous_status' => $previousStatus,
                    'new_status' => 'completed',
                    'company_id' => $payment->company_id,
                    'changed_by_user_id' => $actingUser->id,
                    'reason' => 'Payment fully allocated',
                ]);
            }

            // Create comprehensive audit log entry
            $this->audit('payment.allocated', [
                'payment_id' => $payment->id,
                'payment_number' => $payment->payment_number,
                'total_allocated' => $totalToAllocate,
                'allocation_count' => count($allocations),
                'allocation_method' => $method,
                'allocation_strategy' => $strategy,
                'company_id' => $payment->company_id,
                'allocated_by_user_id' => $actingUser->id,
                'before_states' => $beforeStates,
                'after_states' => $this->getAfterStates($allocations),
                'ip_address' => $this->context->getIpAddress(),
                'user_agent' => $this->context->getUserAgent(),
            ]);

            Log::info('Payment allocated across multiple invoices', [
                'payment_id' => $payment->id,
                'total_allocated' => $totalToAllocate,
                'allocation_count' => count($allocations),
                'user_id' => $actingUser->id,
                'company_id' => $payment->company_id,
                'ip' => $this->context->getIpAddress(),
            ]);

            return $results;
        });
    }

    /**
     * Apply automatic allocation strategy.
     */
    public function applyAllocationStrategy(
        Payment $payment,
        string $strategy,
        ?User $user = null,
        array $options = []
    ): array {
        // Validate company access
        $this->validateCompanyAccess($payment->company_id);

        // Set RLS context
        $this->setRlsContext($payment->company_id);

        // Use user from context if not provided
        $actingUser = $user ?? $this->context->getUser();

        // Get unpaid invoices for the customer
        $unpaidInvoices = $this->getUnpaidInvoicesForCustomer($payment->company, $payment->customer_id, $strategy);

        if ($unpaidInvoices->isEmpty()) {
            $this->audit('payment.allocation_failed', [
                'payment_id' => $payment->id,
                'reason' => 'No unpaid invoices found for customer',
                'strategy' => $strategy,
                'company_id' => $payment->company_id,
                'user_id' => $actingUser->id,
            ]);

            return [];
        }

        // Use the strategy service to calculate allocations
        $allocations = match ($strategy) {
            'fifo' => $this->strategyService->fifo($unpaidInvoices, $payment->remaining_amount),
            'proportional' => $this->strategyService->proportional($unpaidInvoices, $payment->remaining_amount),
            'overdue_first' => $this->strategyService->overdueFirst($unpaidInvoices, $payment->remaining_amount),
            'largest_first' => $this->strategyService->largestFirst($unpaidInvoices, $payment->remaining_amount),
            'percentage_based' => $this->strategyService->percentageBased(
                $unpaidInvoices,
                $payment->remaining_amount,
                $options['percentages'] ?? []
            ),
            'equal_distribution' => $this->strategyService->equalDistribution($unpaidInvoices, $payment->remaining_amount),
            'custom_priority' => $this->strategyService->customPriority(
                $unpaidInvoices,
                $payment->remaining_amount,
                $options['priority_order'] ?? []
            ),
            default => $this->calculateAllocations($unpaidInvoices, $payment->remaining_amount, $strategy, $options),
        };

        if (empty($allocations)) {
            $this->audit('payment.allocation_failed', [
                'payment_id' => $payment->id,
                'reason' => 'Strategy returned no allocations',
                'strategy' => $strategy,
                'available_amount' => $payment->remaining_amount,
                'company_id' => $payment->company_id,
                'user_id' => $actingUser->id,
            ]);

            return [];
        }

        return $this->allocatePaymentAcrossInvoices(
            $payment,
            $allocations,
            $actingUser,
            'automatic',
            $strategy
        );
    }

    /**
     * Reverse a payment allocation.
     */
    public function reverseAllocation(
        PaymentAllocation $allocation,
        string $reason,
        User $user
    ): void {
        DB::transaction(function () use ($allocation, $reason, $user) {
            $allocation->reverse($reason, $user);

            // Update payment status if no longer fully allocated
            $payment = $allocation->payment;
            if (! $payment->is_fully_allocated && $payment->status === 'completed') {
                $payment->status = 'pending';
                $payment->save();
            }

            Log::info('Payment allocation reversed', [
                'allocation_id' => $allocation->id,
                'reason' => $reason,
                'user_id' => $user->id,
            ]);
        });
    }

    /**
     * Get allocation summary for a payment.
     */
    public function getPaymentAllocationSummary(Payment $payment): array
    {
        $allocations = $payment->activeAllocations()
            ->with('invoice')
            ->get();

        return [
            'payment_id' => $payment->id,
            'payment_number' => $payment->payment_number,
            'total_amount' => $payment->amount,
            'total_allocated' => $payment->total_allocated,
            'remaining_amount' => $payment->remaining_amount,
            'is_fully_allocated' => $payment->is_fully_allocated,
            'allocation_count' => $allocations->count(),
            'allocations' => $allocations->map(function ($allocation) {
                return [
                    'id' => $allocation->id,
                    'invoice_id' => $allocation->invoice_id,
                    'invoice_number' => $allocation->invoice->invoice_number,
                    'allocated_amount' => $allocation->allocated_amount,
                    'allocation_date' => $allocation->allocation_date->toISOString(),
                    'allocation_method' => $allocation->allocation_method,
                    'allocation_strategy' => $allocation->allocation_strategy,
                    'notes' => $allocation->notes,
                ];
            })->toArray(),
        ];
    }

    /**
     * Get available allocation strategies.
     */
    public function getAvailableStrategies(): array
    {
        return $this->strategyService->getAvailableStrategies();
    }

    /**
     * Get customer balance summary.
     */
    public function getCustomerBalanceSummary(Company $company, string $customerId): array
    {
        $unpaidInvoices = Invoice::where('company_id', $company->id)
            ->where('customer_id', $customerId)
            ->where('status', '!=', 'paid')
            ->where('status', '!=', 'cancelled')
            ->with(['activePaymentAllocations', 'allocatedPayments'])
            ->get();

        $totalBalanceDue = $unpaidInvoices->sum('balance_due');
        $totalAllocated = $unpaidInvoices->sum('total_allocated');
        $unallocatedPayments = Payment::where('company_id', $company->id)
            ->where('customer_id', $customerId)
            ->where('status', 'completed')
            ->get()
            ->sum('remaining_amount');

        return [
            'customer_id' => $customerId,
            'total_invoices' => $unpaidInvoices->count(),
            'total_balance_due' => $totalBalanceDue,
            'total_allocated' => $totalAllocated,
            'unallocated_payments' => $unallocatedPayments,
            'net_balance' => $totalBalanceDue - $totalAllocated - $unallocatedPayments,
            'invoices' => $unpaidInvoices->map(function ($invoice) {
                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'issue_date' => $invoice->issue_date->toISOString(),
                    'due_date' => $invoice->due_date->toISOString(),
                    'total_amount' => $invoice->total_amount,
                    'balance_due' => $invoice->balance_due,
                    'total_allocated' => $invoice->total_allocated,
                    'payment_status' => $invoice->payment_status,
                    'is_overdue' => $invoice->is_overdue,
                    'days_overdue' => $invoice->days_overdue,
                ];
            })->toArray(),
        ];
    }

    /**
     * Create a payment allocation.
     */
    private function createAllocation(
        Payment $payment,
        Invoice $invoice,
        float $amount,
        string $method,
        ?string $strategy,
        ?string $notes,
        User $user
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
            'created_by_user_id' => $user->id,
        ]);

        // Update invoice balance
        $invoice->calculateTotals();

        return $allocation;
    }

    /**
     * Get unpaid invoices for a customer based on strategy.
     */
    private function getUnpaidInvoicesForCustomer(
        Company $company,
        string $customerId,
        string $strategy
    ): Collection {
        $query = Invoice::where('company_id', $company->id)
            ->where('customer_id', $customerId)
            ->where('status', '!=', 'paid')
            ->where('status', '!=', 'cancelled')
            ->where('balance_due', '>', 0);

        return match ($strategy) {
            'fifo' => $query->orderBy('due_date', 'asc')->get(),
            'due_date' => $query->orderBy('due_date', 'asc')->get(),
            'amount' => $query->orderBy('balance_due', 'desc')->get(),
            'overdue_first' => $query->orderByRaw('CASE WHEN due_date < NOW() THEN 0 ELSE 1 END, due_date ASC')->get(),
            default => $query->orderBy('created_at', 'asc')->get(),
        };
    }

    /**
     * Calculate allocation amounts based on strategy.
     */
    private function calculateAllocations(
        Collection $invoices,
        float $availableAmount,
        string $strategy,
        array $options = []
    ): array {
        $allocations = [];
        $remainingAmount = $availableAmount;

        foreach ($invoices as $invoice) {
            if ($remainingAmount <= 0) {
                break;
            }

            $allocateAmount = min($remainingAmount, $invoice->balance_due);

            if ($allocateAmount > 0) {
                $allocations[] = [
                    'invoice_id' => $invoice->id,
                    'amount' => $allocateAmount,
                    'notes' => "Automatic allocation using {$strategy} strategy",
                ];

                $remainingAmount -= $allocateAmount;
            }
        }

        return $allocations;
    }

    /**
     * Validate user can access the company
     */
    private function validateCompanyAccess(string $companyId): void
    {
        $user = $this->context->getUser();

        if (! $user) {
            throw new \InvalidArgumentException('User context is required');
        }

        // Check if user belongs to this company
        if (! $user->companies()->where('company_id', $companyId)->exists()) {
            throw new \InvalidArgumentException('User does not have access to this company');
        }

        // Additional validation for active company membership
        $companyMembership = $user->companies()
            ->where('company_id', $companyId)
            ->wherePivot('is_active', true)
            ->first();

        if (! $companyMembership) {
            throw new \InvalidArgumentException('User access to this company is not active');
        }
    }

    /**
     * Set RLS context for database operations
     */
    private function setRlsContext(string $companyId): void
    {
        DB::statement('SET app.current_company_id = ?', [$companyId]);
        DB::statement('SET app.current_user_id = ?', [$this->context->getUserId()]);
    }

    /**
     * Get after states for audit logging
     */
    private function getAfterStates(array $allocations): array
    {
        $afterStates = [];

        foreach ($allocations as $allocation) {
            $invoice = Invoice::find($allocation['invoice_id']);
            if ($invoice) {
                $afterStates[$invoice->id] = [
                    'balance_due' => $invoice->balance_due,
                    'total_allocated' => $invoice->total_allocated,
                    'payment_status' => $invoice->payment_status,
                ];
            }
        }

        return $afterStates;
    }
}
