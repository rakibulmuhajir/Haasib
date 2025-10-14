<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    protected PaymentAllocationService $allocationService;
    protected BalanceTrackingService $balanceTrackingService;
    protected PaymentAllocationReversalService $reversalService;
    protected AllocationStrategyService $strategyService;
    protected PaymentAllocationReportService $reportService;

    public function __construct(
        PaymentAllocationService $allocationService,
        BalanceTrackingService $balanceTrackingService,
        PaymentAllocationReversalService $reversalService,
        AllocationStrategyService $strategyService,
        PaymentAllocationReportService $reportService
    ) {
        $this->allocationService = $allocationService;
        $this->balanceTrackingService = $balanceTrackingService;
        $this->reversalService = $reversalService;
        $this->strategyService = $strategyService;
        $this->reportService = $reportService;
    }

    /**
     * Create a new payment with automatic allocation options.
     */
    public function createPayment(
        Company $company,
        Customer $customer,
        array $paymentData,
        User $user,
        array $allocationOptions = []
    ): Payment {
        return DB::transaction(function () use ($company, $customer, $paymentData, $user, $allocationOptions) {
            // Generate payment number
            $paymentNumber = Payment::generatePaymentNumber($company->id);

            // Create payment
            $payment = Payment::create([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'payment_number' => $paymentNumber,
                'payment_date' => $paymentData['payment_date'] ?? now(),
                'payment_method' => $paymentData['payment_method'],
                'reference_number' => $paymentData['reference_number'] ?? null,
                'amount' => $paymentData['amount'],
                'currency' => $paymentData['currency'] ?? $company->currency,
                'status' => 'pending',
                'notes' => $paymentData['notes'] ?? null,
                'created_by_user_id' => $user->id,
            ]);

            // Log payment creation
            Log::info('Payment created', [
                'payment_id' => $payment->id,
                'payment_number' => $payment->payment_number,
                'amount' => $payment->amount,
                'customer_id' => $customer->id,
                'user_id' => $user->id,
            ]);

            // Process automatic allocation if requested
            if (!empty($allocationOptions) && isset($allocationOptions['auto_allocate'])) {
                if (isset($allocationOptions['strategy'])) {
                    $allocations = $this->allocationService->applyAllocationStrategy(
                        $payment,
                        $allocationOptions['strategy'],
                        $user,
                        $allocationOptions['strategy_options'] ?? []
                    );
                    
                    Log::info('Automatic allocation applied', [
                        'payment_id' => $payment->id,
                        'strategy' => $allocationOptions['strategy'],
                        'allocations_count' => count($allocations),
                    ]);
                }
            }

            // Clear relevant caches
            $this->balanceTrackingService->clearBalanceCache($company, $customer->id);

            return $payment;
        });
    }

    /**
     * Process payment completion and allocation.
     */
    public function processPaymentCompletion(
        Payment $payment,
        User $user,
        array $allocationOptions = []
    ): array {
        return DB::transaction(function () use ($payment, $user, $allocationOptions) {
            // Mark payment as completed
            $payment->status = 'completed';
            $payment->save();

            // Process allocations
            $results = [];
            
            if ($payment->remaining_amount > 0) {
                if (isset($allocationOptions['manual_allocations'])) {
                    // Manual allocations
                    $results = $this->allocationService->allocatePaymentAcrossInvoices(
                        $payment,
                        $allocationOptions['manual_allocations'],
                        $user,
                        'manual'
                    );
                } elseif (isset($allocationOptions['strategy'])) {
                    // Automatic allocation with strategy
                    $results = $this->allocationService->applyAllocationStrategy(
                        $payment,
                        $allocationOptions['strategy'],
                        $user,
                        $allocationOptions['strategy_options'] ?? []
                    );
                }
            }

            return [
                'payment' => $payment->fresh(),
                'allocations' => $results,
                'remaining_amount' => $payment->fresh()->remaining_amount,
                'is_fully_allocated' => $payment->fresh()->is_fully_allocated,
            ];
        });
    }

    /**
     * Get comprehensive payment details with allocations.
     */
    public function getPaymentDetails(Payment $payment): array
    {
        $payment->load(['activeAllocations.invoice.customer', 'allocatedInvoices']);
        
        return [
            'payment' => [
                'id' => $payment->id,
                'payment_number' => $payment->payment_number,
                'customer' => [
                    'id' => $payment->customer->id,
                    'name' => $payment->customer->name,
                ],
                'payment_date' => $payment->payment_date->format('Y-m-d'),
                'payment_method' => $payment->payment_method,
                'reference_number' => $payment->reference_number,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'notes' => $payment->notes,
                'created_at' => $payment->created_at->toISOString(),
            ],
            'allocation_summary' => [
                'total_allocated' => $payment->total_allocated,
                'remaining_amount' => $payment->remaining_amount,
                'is_fully_allocated' => $payment->is_fully_allocated,
                'allocation_count' => $payment->activeAllocations()->count(),
            ],
            'allocations' => $this->allocationService->getPaymentAllocationSummary($payment),
            'unpaid_invoices' => $this->getUnpaidInvoicesForCustomer($payment->company, $payment->customer_id),
        ];
    }

    /**
     * Get available allocation strategies.
     */
    public function getAvailableStrategies(): array
    {
        return $this->allocationService->getAvailableStrategies();
    }

    /**
     * Get customer payment summary.
     */
    public function getCustomerPaymentSummary(Company $company, string $customerId): array
    {
        $payments = Payment::where('company_id', $company->id)
            ->where('customer_id', $customerId)
            ->with(['activeAllocations'])
            ->get();

        $totalAmount = $payments->sum('amount');
        $totalAllocated = $payments->sum('total_allocated');
        $completedPayments = $payments->where('status', 'completed')->count();
        $fullyAllocatedPayments = $payments->filter(fn($p) => $p->is_fully_allocated)->count();

        return [
            'customer_id' => $customerId,
            'total_payments' => $payments->count(),
            'completed_payments' => $completedPayments,
            'fully_allocated_payments' => $fullyAllocatedPayments,
            'total_amount' => $totalAmount,
            'total_allocated' => $totalAllocated,
            'unallocated_amount' => $totalAmount - $totalAllocated,
            'allocation_rate' => $totalAmount > 0 ? round(($totalAllocated / $totalAmount) * 100, 2) : 0,
            'payment_details' => $payments->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'payment_number' => $payment->payment_number,
                    'payment_date' => $payment->payment_date->format('Y-m-d'),
                    'amount' => $payment->amount,
                    'status' => $payment->status,
                    'total_allocated' => $payment->total_allocated,
                    'remaining_amount' => $payment->remaining_amount,
                    'is_fully_allocated' => $payment->is_fully_allocated,
                    'allocation_count' => $payment->activeAllocations()->count(),
                ];
            })->toArray(),
        ];
    }

    /**
     * Batch process multiple payments with automatic allocation.
     */
    public function batchProcessPayments(
        array $paymentIds,
        User $user,
        string $allocationStrategy = 'fifo',
        array $options = []
    ): array {
        return DB::transaction(function () use ($paymentIds, $user, $allocationStrategy, $options) {
            $results = [];
            $successCount = 0;
            $failureCount = 0;

            foreach ($paymentIds as $paymentId) {
                try {
                    $payment = Payment::findOrFail($paymentId);
                    
                    if ($payment->status !== 'completed') {
                        $processResult = $this->processPaymentCompletion($payment, $user, [
                            'strategy' => $allocationStrategy,
                            'strategy_options' => $options
                        ]);
                        
                        $results[] = [
                            'payment_id' => $paymentId,
                            'status' => 'success',
                            'allocations_count' => count($processResult['allocations']),
                            'remaining_amount' => $processResult['remaining_amount'],
                        ];
                        $successCount++;
                    } else {
                        // Payment already completed, just check allocations
                        if ($payment->remaining_amount > 0) {
                            $allocations = $this->allocationService->applyAllocationStrategy(
                                $payment,
                                $allocationStrategy,
                                $user,
                                $options
                            );
                            
                            $results[] = [
                                'payment_id' => $paymentId,
                                'status' => 'success',
                                'allocations_count' => count($allocations),
                                'remaining_amount' => $payment->fresh()->remaining_amount,
                            ];
                        } else {
                            $results[] = [
                                'payment_id' => $paymentId,
                                'status' => 'already_processed',
                                'message' => 'Payment was already fully allocated',
                            ];
                        }
                        $successCount++;
                    }
                } catch (\Exception $e) {
                    $results[] = [
                        'payment_id' => $paymentId,
                        'status' => 'error',
                        'error' => $e->getMessage(),
                    ];
                    $failureCount++;
                }
            }

            Log::info('Batch payment processing completed', [
                'total_payments' => count($paymentIds),
                'success_count' => $successCount,
                'failure_count' => $failureCount,
                'strategy' => $allocationStrategy,
                'user_id' => $user->id,
            ]);

            return [
                'total_payments' => count($paymentIds),
                'success_count' => $successCount,
                'failure_count' => $failureCount,
                'results' => $results,
            ];
        });
    }

    /**
     * Reverse payment allocations with validation.
     */
    public function reversePaymentAllocations(
        Payment $payment,
        array $allocationIds,
        string $reason,
        User $user
    ): array {
        return $this->reversalService->reverseMultipleAllocations($allocationIds, $reason, $user);
    }

    /**
     * Get payment allocation impact analysis.
     */
    public function getAllocationImpact(array $allocationIds): array
    {
        return $this->reversalService->getReversalImpact($allocationIds);
    }

    /**
     * Generate payment allocation reports.
     */
    public function generateAllocationReport(
        Company $company,
        \DateTime $startDate,
        \DateTime $endDate,
        array $options = []
    ): array {
        return $this->reportService->generateAllocationReport($company, $startDate, $endDate, $options);
    }

    /**
     * Export payment allocation data.
     */
    public function exportAllocationData(
        Company $company,
        \DateTime $startDate,
        \DateTime $endDate,
        string $format = 'csv',
        array $filters = []
    ): string {
        return $this->reportService->exportAllocationData($company, $startDate, $endDate, $format, $filters);
    }

    /**
     * Get payment analytics and insights.
     */
    public function getPaymentAnalytics(
        Company $company,
        \DateTime $startDate,
        \DateTime $endDate
    ): array {
        $payments = Payment::where('company_id', $company->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $allocations = PaymentAllocation::where('company_id', $company->id)
            ->whereBetween('allocation_date', [$startDate, $endDate])
            ->get();

        return [
            'summary' => [
                'total_payments' => $payments->count(),
                'total_amount' => $payments->sum('amount'),
                'completed_payments' => $payments->where('status', 'completed')->count(),
                'total_allocated' => $allocations->sum('allocated_amount'),
                'allocation_rate' => $payments->sum('amount') > 0 ? 
                    round(($allocations->sum('allocated_amount') / $payments->sum('amount')) * 100, 2) : 0,
            ],
            'by_payment_method' => $payments->groupBy('payment_method')
                ->map(function ($group) {
                    return [
                        'count' => $group->count(),
                        'amount' => $group->sum('amount'),
                        'allocated' => $group->sum(function ($payment) {
                            return $payment->activeAllocations()->sum('allocated_amount');
                        }),
                    ];
                })->toArray(),
            'by_status' => $payments->groupBy('status')
                ->map(function ($group) {
                    return [
                        'count' => $group->count(),
                        'amount' => $group->sum('amount'),
                    ];
                })->toArray(),
            'allocation_metrics' => $this->balanceTrackingService->getAllocationEfficiencyMetrics(
                $company,
                $startDate,
                $endDate
            ),
            'customer_summary' => $this->balanceTrackingService->getCompanyBalanceOverview($company),
        ];
    }

    /**
     * Get payment reconciliation data.
     */
    public function getPaymentReconciliation(Company $company, \DateTime $date): array
    {
        $payments = Payment::where('company_id', $company->id)
            ->whereDate('payment_date', $date)
            ->with(['activeAllocations.invoice'])
            ->get();

        $reconciliationData = $payments->map(function ($payment) {
            return [
                'payment_id' => $payment->id,
                'payment_number' => $payment->payment_number,
                'customer_name' => $payment->customer->name,
                'payment_amount' => $payment->amount,
                'allocated_amount' => $payment->total_allocated,
                'unallocated_amount' => $payment->remaining_amount,
                'allocations' => $payment->activeAllocations->map(function ($allocation) {
                    return [
                        'invoice_number' => $allocation->invoice->invoice_number,
                        'allocated_amount' => $allocation->allocated_amount,
                        'allocation_date' => $allocation->allocation_date->format('Y-m-d'),
                    ];
                })->toArray(),
            ];
        })->toArray();

        $totalPayments = $payments->sum('amount');
        $totalAllocated = $payments->sum('total_allocated');
        $totalUnallocated = $totalPayments - $totalAllocated;

        return [
            'reconciliation_date' => $date->format('Y-m-d'),
            'company_id' => $company->id,
            'summary' => [
                'total_payments' => $payments->count(),
                'total_payment_amount' => $totalPayments,
                'total_allocated' => $totalAllocated,
                'total_unallocated' => $totalUnallocated,
                'reconciliation_rate' => $totalPayments > 0 ? round(($totalAllocated / $totalPayments) * 100, 2) : 0,
            ],
            'details' => $reconciliationData,
            'discrepancies' => $this->findReconciliationDiscrepancies($payments),
        ];
    }

    /**
     * Helper method to get unpaid invoices for a customer.
     */
    private function getUnpaidInvoicesForCustomer(Company $company, string $customerId): Collection
    {
        return Invoice::where('company_id', $company->id)
            ->where('customer_id', $customerId)
            ->where('status', '!=', 'paid')
            ->where('status', '!=', 'cancelled')
            ->where('balance_due', '>', 0)
            ->orderBy('due_date', 'asc')
            ->get();
    }

    /**
     * Find reconciliation discrepancies.
     */
    private function findReconciliationDiscrepancies(Collection $payments): array
    {
        $discrepancies = [];

        foreach ($payments as $payment) {
            // Check for over-allocation (allocated more than payment amount)
            if ($payment->total_allocated > $payment->amount) {
                $discrepancies[] = [
                    'type' => 'over_allocation',
                    'payment_id' => $payment->id,
                    'payment_number' => $payment->payment_number,
                    'payment_amount' => $payment->amount,
                    'allocated_amount' => $payment->total_allocated,
                    'overage' => $payment->total_allocated - $payment->amount,
                ];
            }

            // Check for allocations to cancelled invoices
            $cancelledAllocations = $payment->activeAllocations()
                ->whereHas('invoice', function ($query) {
                    $query->where('status', 'cancelled');
                });

            if ($cancelledAllocations->count() > 0) {
                $discrepancies[] = [
                    'type' => 'cancelled_invoice_allocation',
                    'payment_id' => $payment->id,
                    'payment_number' => $payment->payment_number,
                    'allocated_amount' => $cancelledAllocations->sum('allocated_amount'),
                    'affected_invoices' => $cancelledAllocations->pluck('invoice.invoice_number')->toArray(),
                ];
            }
        }

        return $discrepancies;
    }
}