<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PaymentAllocation;
use App\Models\Payment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PaymentAllocationReportService
{
    /**
     * Generate comprehensive allocation report for a date range.
     */
    public function generateAllocationReport(
        Company $company,
        \DateTime $startDate,
        \DateTime $endDate,
        array $options = []
    ): array {
        $dateRange = [
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
        ];

        return [
            'report_metadata' => [
                'company_id' => $company->id,
                'company_name' => $company->name,
                'generated_at' => now()->toISOString(),
                'date_range' => $dateRange,
                'report_type' => $options['report_type'] ?? 'comprehensive',
            ],
            'summary_metrics' => $this->getSummaryMetrics($company, $startDate, $endDate),
            'allocation_details' => $this->getAllocationDetails($company, $startDate, $endDate, $options),
            'strategy_analysis' => $this->getStrategyAnalysis($company, $startDate, $endDate),
            'customer_breakdown' => $this->getCustomerBreakdown($company, $startDate, $endDate),
            'efficiency_metrics' => $this->getEfficiencyMetrics($company, $startDate, $endDate),
            'trends_analysis' => $this->getTrendsAnalysis($company, $startDate, $endDate),
            'recommendations' => $this->generateRecommendations($company, $startDate, $endDate),
        ];
    }

    /**
     * Generate daily allocation summary report.
     */
    public function generateDailySummaryReport(Company $company, \DateTime $date): array
    {
        $allocations = PaymentAllocation::where('company_id', $company->id)
            ->whereDate('allocation_date', $date)
            ->with(['payment', 'invoice.customer'])
            ->get();

        $totalAllocated = $allocations->sum('allocated_amount');
        $uniqueInvoices = $allocations->pluck('invoice_id')->unique()->count();
        $uniquePayments = $allocations->pluck('payment_id')->unique()->count();

        return [
            'report_metadata' => [
                'company_id' => $company->id,
                'company_name' => $company->name,
                'report_date' => $date->format('Y-m-d'),
                'generated_at' => now()->toISOString(),
                'report_type' => 'daily_summary',
            ],
            'summary' => [
                'total_allocations' => $allocations->count(),
                'total_amount_allocated' => $totalAllocated,
                'unique_invoices' => $uniqueInvoices,
                'unique_payments' => $uniquePayments,
                'average_allocation_amount' => $allocations->count() > 0 ? $totalAllocated / $allocations->count() : 0,
            ],
            'allocations_by_method' => $this->groupAllocationsByMethod($allocations),
            'allocations_by_strategy' => $this->groupAllocationsByStrategy($allocations),
            'top_customers' => $this->getTopCustomersByAllocation($allocations),
            'payment_utilization' => $this->getPaymentUtilization($allocations),
        ];
    }

    /**
     * Generate customer allocation report.
     */
    public function generateCustomerReport(
        Company $company,
        string $customerId,
        \DateTime $startDate,
        \DateTime $endDate
    ): array {
        $customer = Customer::findOrFail($customerId);
        $allocations = PaymentAllocation::where('company_id', $company->id)
            ->whereHas('invoice', function ($query) use ($customerId) {
                $query->where('customer_id', $customerId);
            })
            ->whereBetween('allocation_date', [$startDate, $endDate])
            ->with(['payment', 'invoice'])
            ->get();

        return [
            'report_metadata' => [
                'company_id' => $company->id,
                'company_name' => $company->name,
                'customer_id' => $customerId,
                'customer_name' => $customer->name,
                'date_range' => [
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                ],
                'generated_at' => now()->toISOString(),
                'report_type' => 'customer_specific',
            ],
            'customer_summary' => $this->getCustomerAllocationSummary($customerId, $allocations),
            'allocation_timeline' => $this->getCustomerAllocationTimeline($allocations),
            'balance_impact' => $this->getCustomerBalanceImpact($customerId, $allocations),
            'efficiency_metrics' => $this->getCustomerEfficiencyMetrics($allocations),
        ];
    }

    /**
     * Export allocation data in various formats.
     */
    public function exportAllocationData(
        Company $company,
        \DateTime $startDate,
        \DateTime $endDate,
        string $format = 'csv',
        array $filters = []
    ): string {
        $query = PaymentAllocation::where('company_id', $company->id)
            ->whereBetween('allocation_date', [$startDate, $endDate])
            ->with(['payment', 'invoice.customer']);

        // Apply filters
        if (isset($filters['customer_id'])) {
            $query->whereHas('invoice', function ($q) use ($filters) {
                $q->where('customer_id', $filters['customer_id']);
            });
        }

        if (isset($filters['allocation_strategy'])) {
            $query->where('allocation_strategy', $filters['allocation_strategy']);
        }

        if (isset($filters['allocation_method'])) {
            $query->where('allocation_method', $filters['allocation_method']);
        }

        $allocations = $query->get();

        return match ($format) {
            'csv' => $this->exportToCsv($allocations),
            'json' => $this->exportToJson($allocations),
            'excel' => $this->exportToExcel($allocations),
            default => throw new \InvalidArgumentException("Unsupported export format: {$format}"),
        };
    }

    /**
     * Get summary metrics for the report.
     */
    private function getSummaryMetrics(Company $company, \DateTime $startDate, \DateTime $endDate): array
    {
        $allocations = PaymentAllocation::where('company_id', $company->id)
            ->whereBetween('allocation_date', [$startDate, $endDate])
            ->get();

        $totalAllocated = $allocations->sum('allocated_amount');
        $totalPayments = Payment::where('company_id', $company->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'completed')
            ->sum('amount');

        return [
            'total_allocations' => $allocations->count(),
            'total_amount_allocated' => $totalAllocated,
            'total_payments_received' => $totalPayments,
            'allocation_rate' => $totalPayments > 0 ? round(($totalAllocated / $totalPayments) * 100, 2) : 0,
            'unique_invoices' => $allocations->pluck('invoice_id')->unique()->count(),
            'unique_customers' => $allocations->pluck('invoice.customer_id')->unique()->count(),
            'average_allocation_size' => $allocations->count() > 0 ? $totalAllocated / $allocations->count() : 0,
        ];
    }

    /**
     * Get detailed allocation information.
     */
    private function getAllocationDetails(Company $company, \DateTime $startDate, \DateTime $endDate, array $options): array
    {
        $query = PaymentAllocation::where('company_id', $company->id)
            ->whereBetween('allocation_date', [$startDate, $endDate])
            ->with(['payment', 'invoice.customer']);

        if (isset($options['limit'])) {
            $query->limit($options['limit']);
        }

        $allocations = $query->orderBy('allocation_date', 'desc')->get();

        return [
            'total_records' => $allocations->count(),
            'data' => $allocations->map(function ($allocation) {
                return [
                    'id' => $allocation->id,
                    'allocation_date' => $allocation->allocation_date->toISOString(),
                    'allocated_amount' => $allocation->allocated_amount,
                    'allocation_method' => $allocation->allocation_method,
                    'allocation_strategy' => $allocation->allocation_strategy,
                    'notes' => $allocation->notes,
                    'payment' => [
                        'id' => $allocation->payment->id,
                        'payment_number' => $allocation->payment->payment_number,
                        'payment_date' => $allocation->payment->payment_date->format('Y-m-d'),
                        'amount' => $allocation->payment->amount,
                        'payment_method' => $allocation->payment->payment_method,
                    ],
                    'invoice' => [
                        'id' => $allocation->invoice->id,
                        'invoice_number' => $allocation->invoice->invoice_number,
                        'issue_date' => $allocation->invoice->issue_date->format('Y-m-d'),
                        'due_date' => $allocation->invoice->due_date->format('Y-m-d'),
                        'total_amount' => $allocation->invoice->total_amount,
                        'balance_due_after' => $allocation->invoice->balance_due,
                    ],
                    'customer' => [
                        'id' => $allocation->invoice->customer->id,
                        'name' => $allocation->invoice->customer->name,
                    ],
                ];
            })->toArray(),
        ];
    }

    /**
     * Analyze allocation strategies usage.
     */
    private function getStrategyAnalysis(Company $company, \DateTime $startDate, \DateTime $endDate): array
    {
        $strategies = PaymentAllocation::whereHas('payment', function ($query) use ($company, $startDate, $endDate) {
                $query->where('company_id', $company->id)
                    ->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->whereNotNull('allocation_strategy')
            ->groupBy('allocation_strategy')
            ->selectRaw('
                allocation_strategy,
                COUNT(*) as usage_count,
                SUM(allocated_amount) as total_amount,
                AVG(allocated_amount) as average_amount,
                MIN(allocated_amount) as min_amount,
                MAX(allocated_amount) as max_amount
            ')
            ->get();

        $manualAllocations = PaymentAllocation::whereHas('payment', function ($query) use ($company, $startDate, $endDate) {
                $query->where('company_id', $company->id)
                    ->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->whereNull('allocation_strategy')
            ->count();

        $manualAmount = PaymentAllocation::whereHas('payment', function ($query) use ($company, $startDate, $endDate) {
                $query->where('company_id', $company->id)
                    ->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->whereNull('allocation_strategy')
            ->sum('allocated_amount');

        return [
            'automatic_strategies' => $strategies->toArray(),
            'manual_allocations' => [
                'count' => $manualAllocations,
                'total_amount' => $manualAmount,
                'average_amount' => $manualAllocations > 0 ? $manualAmount / $manualAllocations : 0,
            ],
            'strategy_efficiency' => $this->calculateStrategyEfficiency($strategies),
        ];
    }

    /**
     * Get customer breakdown analysis.
     */
    private function getCustomerBreakdown(Company $company, \DateTime $startDate, \DateTime $endDate): array
    {
        $customerAllocations = PaymentAllocation::where('company_id', $company->id)
            ->whereBetween('allocation_date', [$startDate, $endDate])
            ->join('acct.invoices', 'acct.payment_allocations.invoice_id', '=', 'acct.invoices.id')
            ->join('acct.customers', 'acct.invoices.customer_id', '=', 'acct.customers.id')
            ->groupBy('acct.customers.id', 'acct.customers.name')
            ->selectRaw('
                acct.customers.id as customer_id,
                acct.customers.name as customer_name,
                COUNT(*) as allocation_count,
                SUM(acct.payment_allocations.allocated_amount) as total_allocated,
                AVG(acct.payment_allocations.allocated_amount) as average_allocation,
                COUNT(DISTINCT acct.payment_allocations.invoice_id) as unique_invoices
            ')
            ->orderBy('total_allocated', 'desc')
            ->get();

        return [
            'total_customers' => $customerAllocations->count(),
            'top_customers' => $customerAllocations->take(10)->toArray(),
            'distribution' => [
                'top_10_customers_percentage' => $this->calculateTopPercentage($customerAllocations, 10),
                'top_20_customers_percentage' => $this->calculateTopPercentage($customerAllocations, 20),
            ],
        ];
    }

    /**
     * Calculate efficiency metrics.
     */
    private function getEfficiencyMetrics(Company $company, \DateTime $startDate, \DateTime $endDate): array
    {
        $allocations = PaymentAllocation::where('company_id', $company->id)
            ->whereBetween('allocation_date', [$startDate, $endDate])
            ->with(['payment', 'invoice'])
            ->get();

        $payments = Payment::where('company_id', $company->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'completed')
            ->get();

        // Calculate time to allocation
        $allocationTimes = $allocations->map(function ($allocation) {
            return $allocation->created_at->diffInHours($allocation->payment->created_at);
        })->filter();

        return [
            'average_time_to_allocate' => $allocationTimes->count() > 0 ? round($allocationTimes->avg(), 2) : 0,
            'allocation_completion_rate' => $this->calculateAllocationCompletionRate($payments),
            'allocation_accuracy' => $this->calculateAllocationAccuracy($allocations),
            'reversal_rate' => $this->calculateReversalRate($allocations),
        ];
    }

    /**
     * Analyze trends over time.
     */
    private function getTrendsAnalysis(Company $company, \DateTime $startDate, \DateTime $endDate): array
    {
        $days = $startDate->diffInDays($endDate) + 1;
        $dailyData = [];

        for ($i = 0; $i < $days; $i++) {
            $date = $startDate->copy()->addDays($i);
            $dayAllocations = PaymentAllocation::where('company_id', $company->id)
                ->whereDate('allocation_date', $date)
                ->get();

            $dailyData[] = [
                'date' => $date->format('Y-m-d'),
                'allocations_count' => $dayAllocations->count(),
                'amount_allocated' => $dayAllocations->sum('allocated_amount'),
                'unique_customers' => $dayAllocations->pluck('invoice.customer_id')->unique()->count(),
            ];
        }

        return [
            'daily_data' => $dailyData,
            'trends' => [
                'average_daily_allocations' => collect($dailyData)->avg('allocations_count'),
                'average_daily_amount' => collect($dailyData)->avg('amount_allocated'),
                'peak_allocation_day' => collect($dailyData)->sortByDesc('amount_allocated')->first(),
                'growth_trend' => $this->calculateGrowthTrend($dailyData),
            ],
        ];
    }

    /**
     * Generate recommendations based on data.
     */
    private function generateRecommendations(Company $company, \DateTime $startDate, \DateTime $endDate): array
    {
        $recommendations = [];
        
        // Analyze allocation rates
        $allocationRate = $this->calculateAllocationRate($company, $startDate, $endDate);
        if ($allocationRate < 80) {
            $recommendations[] = [
                'type' => 'efficiency',
                'priority' => 'high',
                'title' => 'Improve Allocation Rate',
                'description' => "Current allocation rate is {$allocationRate}%. Consider implementing automatic allocation strategies to improve efficiency.",
            ];
        }

        // Analyze reversal rates
        $reversalRate = $this->calculateReversalRate(
            PaymentAllocation::where('company_id', $company->id)
                ->whereBetween('allocation_date', [$startDate, $endDate])
                ->get()
        );

        if ($reversalRate > 10) {
            $recommendations[] = [
                'type' => 'quality',
                'priority' => 'medium',
                'title' => 'Review Allocation Process',
                'description' => "High reversal rate of {$reversalRate}% detected. Review allocation validation and approval processes.",
            ];
        }

        return $recommendations;
    }

    /**
     * Helper methods for calculations and exports
     */
    private function groupAllocationsByMethod(Collection $allocations): array
    {
        return $allocations->groupBy('allocation_method')
            ->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'total_amount' => $group->sum('allocated_amount'),
                ];
            })
            ->toArray();
    }

    private function groupAllocationsByStrategy(Collection $allocations): array
    {
        return $allocations->whereNotNull('allocation_strategy')
            ->groupBy('allocation_strategy')
            ->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'total_amount' => $group->sum('allocated_amount'),
                ];
            })
            ->toArray();
    }

    private function getTopCustomersByAllocation(Collection $allocations): array
    {
        return $allocations->groupBy('invoice.customer_id')
            ->map(function ($group, $customerId) {
                return [
                    'customer_id' => $customerId,
                    'customer_name' => $group->first()->invoice->customer->name,
                    'total_allocated' => $group->sum('allocated_amount'),
                    'allocation_count' => $group->count(),
                ];
            })
            ->sortByDesc('total_allocated')
            ->take(10)
            ->values()
            ->toArray();
    }

    private function getPaymentUtilization(Collection $allocations): array
    {
        $paymentTotals = $allocations->groupBy('payment_id')
            ->map(function ($group) {
                $payment = $group->first()->payment;
                return [
                    'payment_id' => $payment->id,
                    'payment_number' => $payment->payment_number,
                    'payment_amount' => $payment->amount,
                    'allocated_amount' => $group->sum('allocated_amount'),
                    'utilization_rate' => round(($group->sum('allocated_amount') / $payment->amount) * 100, 2),
                ];
            })
            ->values();

        return [
            'average_utilization' => $paymentTotals->avg('utilization_rate'),
            'fully_utilized_payments' => $paymentTotals->where('utilization_rate', '>=', 99)->count(),
            'underutilized_payments' => $paymentTotals->where('utilization_rate', '<', 80)->count(),
            'payments' => $paymentTotals->toArray(),
        ];
    }

    private function exportToCsv(Collection $allocations): string
    {
        $csv = "Allocation Date,Payment Number,Invoice Number,Customer,Amount,Method,Strategy,Notes\n";
        
        foreach ($allocations as $allocation) {
            $csv .= sprintf(
                "%s,%s,%s,%s,%.2f,%s,%s,\"%s\"\n",
                $allocation->allocation_date->format('Y-m-d'),
                $allocation->payment->payment_number,
                $allocation->invoice->invoice_number,
                $allocation->invoice->customer->name,
                $allocation->allocated_amount,
                $allocation->allocation_method,
                $allocation->allocation_strategy ?? '',
                str_replace('"', '""', $allocation->notes ?? '')
            );
        }

        return $csv;
    }

    private function exportToJson(Collection $allocations): string
    {
        return $allocations->map(function ($allocation) {
            return [
                'allocation_date' => $allocation->allocation_date->toISOString(),
                'payment_number' => $allocation->payment->payment_number,
                'invoice_number' => $allocation->invoice->invoice_number,
                'customer_name' => $allocation->invoice->customer->name,
                'allocated_amount' => $allocation->allocated_amount,
                'allocation_method' => $allocation->allocation_method,
                'allocation_strategy' => $allocation->allocation_strategy,
                'notes' => $allocation->notes,
            ];
        })->toJson();
    }

    private function exportToExcel(Collection $allocations): string
    {
        // For simplicity, return CSV format (in a real implementation, use a proper Excel library)
        return $this->exportToCsv($allocations);
    }

    private function calculateStrategyEfficiency(Collection $strategies): array
    {
        $totalAmount = $strategies->sum('total_amount');
        
        return $strategies->map(function ($strategy) use ($totalAmount) {
            return [
                'strategy' => $strategy->allocation_strategy,
                'percentage_of_total' => $totalAmount > 0 ? round(($strategy->total_amount / $totalAmount) * 100, 2) : 0,
                'efficiency_score' => $this->calculateStrategyScore($strategy),
            ];
        })->toArray();
    }

    private function calculateStrategyScore($strategy): float
    {
        // Simple scoring based on average allocation size and consistency
        $score = 50; // Base score
        
        if ($strategy->average_amount > 500) $score += 20;
        if ($strategy->usage_count > 10) $score += 20;
        if ($strategy->min_amount > 0) $score += 10;
        
        return min(100, $score);
    }

    private function calculateTopPercentage(Collection $data, int $topN): float
    {
        $total = $data->sum('total_allocated');
        $topTotal = $data->take($topN)->sum('total_allocated');
        
        return $total > 0 ? round(($topTotal / $total) * 100, 2) : 0;
    }

    private function calculateAllocationCompletionRate(Collection $payments): float
    {
        $completedPayments = $payments->filter(fn($p) => $p->is_fully_allocated)->count();
        
        return $payments->count() > 0 ? round(($completedPayments / $payments->count()) * 100, 2) : 0;
    }

    private function calculateAllocationAccuracy(Collection $allocations): float
    {
        // Simple accuracy metric based on allocation notes and strategy consistency
        $withNotes = $allocations->whereNotNull('notes')->count();
        $withStrategy = $allocations->whereNotNull('allocation_strategy')->count();
        
        return $allocations->count() > 0 ? round((($withNotes + $withStrategy) / ($allocations->count() * 2)) * 100, 2) : 0;
    }

    private function calculateReversalRate(Collection $allocations): float
    {
        $reversed = $allocations->whereNotNull('reversed_at')->count();
        
        return $allocations->count() > 0 ? round(($reversed / $allocations->count()) * 100, 2) : 0;
    }

    private function calculateAllocationRate(Company $company, \DateTime $startDate, \DateTime $endDate): float
    {
        $totalPayments = Payment::where('company_id', $company->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'completed')
            ->sum('amount');

        $totalAllocated = PaymentAllocation::where('company_id', $company->id)
            ->whereBetween('allocation_date', [$startDate, $endDate])
            ->sum('allocated_amount');

        return $totalPayments > 0 ? round(($totalAllocated / $totalPayments) * 100, 2) : 0;
    }

    private function calculateGrowthTrend(array $dailyData): array
    {
        $firstHalf = array_slice($dailyData, 0, count($dailyData) / 2);
        $secondHalf = array_slice($dailyData, count($dailyData) / 2);

        $firstHalfAvg = collect($firstHalf)->avg('amount_allocated');
        $secondHalfAvg = collect($secondHalf)->avg('amount_allocated');

        $trend = 'stable';
        if ($secondHalfAvg > $firstHalfAvg * 1.1) {
            $trend = 'increasing';
        } elseif ($secondHalfAvg < $firstHalfAvg * 0.9) {
            $trend = 'decreasing';
        }

        return [
            'trend' => $trend,
            'percentage_change' => $firstHalfAvg > 0 ? round((($secondHalfAvg - $firstHalfAvg) / $firstHalfAvg) * 100, 2) : 0,
        ];
    }

    private function getCustomerAllocationSummary(string $customerId, Collection $allocations): array
    {
        return [
            'total_allocations' => $allocations->count(),
            'total_amount_allocated' => $allocations->sum('allocated_amount'),
            'unique_payments' => $allocations->pluck('payment_id')->unique()->count(),
            'unique_invoices' => $allocations->pluck('invoice_id')->unique()->count(),
            'average_allocation_size' => $allocations->count() > 0 ? $allocations->sum('allocated_amount') / $allocations->count() : 0,
        ];
    }

    private function getCustomerAllocationTimeline(Collection $allocations): array
    {
        return $allocations->groupBy(function ($allocation) {
            return $allocation->allocation_date->format('Y-m');
        })->map(function ($monthAllocations, $month) {
            return [
                'month' => $month,
                'allocations_count' => $monthAllocations->count(),
                'total_amount' => $monthAllocations->sum('allocated_amount'),
            ];
        })->sortBy('month')->values()->toArray();
    }

    private function getCustomerBalanceImpact(string $customerId, Collection $allocations): array
    {
        // This would calculate how allocations affected the customer's balance over time
        return [
            'pre_allocation_balance' => 0, // Would calculate based on invoices before allocations
            'post_allocation_balance' => 0, // Would calculate based on current state
            'balance_reduction' => $allocations->sum('allocated_amount'),
        ];
    }

    private function getCustomerEfficiencyMetrics(Collection $allocations): array
    {
        return [
            'allocation_rate' => 100, // Would calculate based on customer's payments
            'average_allocation_size' => $allocations->count() > 0 ? $allocations->sum('allocated_amount') / $allocations->count() : 0,
            'allocation_frequency' => 'monthly', // Would calculate based on allocation patterns
        ];
    }
}
