<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class BalanceTrackingService
{
    /**
     * Get real-time customer balance summary.
     */
    public function getCustomerBalanceSummary(Company $company, string $customerId, bool $useCache = true): array
    {
        $cacheKey = "customer_balance_{$company->id}_{$customerId}";
        
        if ($useCache) {
            return Cache::remember($cacheKey, 300, function () use ($company, $customerId) {
                return $this->calculateCustomerBalance($company, $customerId);
            });
        }

        return $this->calculateCustomerBalance($company, $customerId);
    }

    /**
     * Get balance history for a customer over time.
     */
    public function getCustomerBalanceHistory(
        Company $company, 
        string $customerId, 
        \DateTime $startDate, 
        \DateTime $endDate,
        string $period = 'daily'
    ): array {
        // Generate date periods based on requested granularity
        $periods = $this->generateDatePeriods($startDate, $endDate, $period);
        
        $balanceHistory = [];

        foreach ($periods as $period) {
            $balanceData = $this->getBalanceAtDate($company, $customerId, $period['date']);
            $balanceHistory[] = [
                'date' => $period['date']->format('Y-m-d'),
                'period_start' => $period['start']->format('Y-m-d'),
                'period_end' => $period['end']->format('Y-m-d'),
                'total_balance_due' => $balanceData['total_balance_due'],
                'total_allocated' => $balanceData['total_allocated'],
                'unallocated_amount' => $balanceData['unallocated_amount'],
                'net_balance' => $balanceData['net_balance'],
                'invoice_count' => $balanceData['invoice_count'],
                'payment_count' => $balanceData['payment_count'],
            ];
        }

        return $balanceHistory;
    }

    /**
     * Get company-wide balance overview.
     */
    public function getCompanyBalanceOverview(Company $company): array
    {
        $cacheKey = "company_balance_overview_{$company->id}";
        
        return Cache::remember($cacheKey, 600, function () use ($company) {
            $totalBalanceDue = Invoice::where('company_id', $company->id)
                ->where('status', '!=', 'paid')
                ->where('status', '!=', 'cancelled')
                ->sum('balance_due');

        $totalAllocated = Invoice::join('acct.payment_allocations', 'acct.payment_allocations.invoice_id', '=', 'acct.invoices.id')
                ->join('acct.payments', 'acct.payment_allocations.payment_id', '=', 'acct.payments.id')
                ->where('acct.invoices.company_id', $company->id)
                ->where('acct.invoices.status', '!=', 'paid')
                ->where('acct.invoices.status', '!=', 'cancelled')
                ->whereNull('acct.payment_allocations.reversed_at')
                ->sum('acct.payment_allocations.allocated_amount');

            $unallocatedPayments = Payment::where('company_id', $company->id)
                ->where('status', 'completed')
                ->sum('remaining_amount');

            $invoiceStats = $this->getInvoiceStatistics($company);
            $paymentStats = $this->getPaymentStatistics($company);

            return [
                'total_balance_due' => $totalBalanceDue,
                'total_allocated' => $totalAllocated,
                'unallocated_amount' => $unallocatedPayments,
                'net_balance' => $totalBalanceDue - $totalAllocated - $unallocatedPayments,
                'customer_count' => Customer::where('company_id', $company->id)->count(),
                'invoice_statistics' => $invoiceStats,
                'payment_statistics' => $paymentStats,
                'aging_summary' => $this->getAgingSummary($company),
                'updated_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Get aging report for company.
     */
    public function getAgingReport(Company $company, ?string $customerId = null): array
    {
        $query = Invoice::where('company_id', $company->id)
            ->where('status', '!=', 'paid')
            ->where('status', '!=', 'cancelled')
            ->where('balance_due', '>', 0);

        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        $invoices = $query->with(['customer'])->get();

        $agingBuckets = [
            'current' => ['amount' => 0, 'count' => 0, 'invoices' => []],
            '1_30_days' => ['amount' => 0, 'count' => 0, 'invoices' => []],
            '31_60_days' => ['amount' => 0, 'count' => 0, 'invoices' => []],
            '61_90_days' => ['amount' => 0, 'count' => 0, 'invoices' => []],
            'over_90_days' => ['amount' => 0, 'count' => 0, 'invoices' => []],
        ];

        foreach ($invoices as $invoice) {
            $daysOverdue = max(0, $invoice->days_overdue);
            $bucket = $this->getAgingBucket($daysOverdue);
            
            $agingBuckets[$bucket]['amount'] += $invoice->balance_due;
            $agingBuckets[$bucket]['count']++;
            $agingBuckets[$bucket]['invoices'][] = [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'customer_name' => $invoice->customer->name,
                'due_date' => $invoice->due_date->format('Y-m-d'),
                'days_overdue' => $daysOverdue,
                'balance_due' => $invoice->balance_due,
            ];
        }

        return [
            'company_id' => $company->id,
            'customer_id' => $customerId,
            'generated_at' => now()->toISOString(),
            'total_amount_due' => array_sum(array_column($agingBuckets, 'amount')),
            'total_invoices' => array_sum(array_column($agingBuckets, 'count')),
            'buckets' => $agingBuckets,
        ];
    }

    /**
     * Get payment allocation efficiency metrics.
     */
    public function getAllocationEfficiencyMetrics(Company $company, \DateTime $startDate, \DateTime $endDate): array
    {
        $payments = Payment::where('company_id', $company->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'completed')
            ->with(['activeAllocations'])
            ->get();

        $totalPaymentAmount = $payments->sum('amount');
        $totalAllocatedAmount = $payments->sum('total_allocated');
        $totalUnallocatedAmount = $payments->sum('remaining_amount');

        $fullyAllocatedPayments = $payments->filter(fn($p) => $p->is_fully_allocated)->count();
        $partiallyAllocatedPayments = $payments->filter(fn($p) => $p->total_allocated > 0 && !$p->is_fully_allocated)->count();
        $unallocatedPayments = $payments->filter(fn($p) => $p->total_allocated == 0)->count();

        // Calculate allocation strategy usage
        $strategies = PaymentAllocation::whereHas('payment', function ($query) use ($company, $startDate, $endDate) {
                $query->where('company_id', $company->id)
                    ->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->whereNotNull('allocation_strategy')
            ->groupBy('allocation_strategy')
            ->selectRaw('allocation_strategy, COUNT(*) as usage_count, SUM(allocated_amount) as total_amount')
            ->get()
            ->keyBy('allocation_strategy');

        return [
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ],
            'payment_summary' => [
                'total_payments' => $payments->count(),
                'total_amount' => $totalPaymentAmount,
                'total_allocated' => $totalAllocatedAmount,
                'total_unallocated' => $totalUnallocatedAmount,
                'allocation_rate' => $totalPaymentAmount > 0 ? round(($totalAllocatedAmount / $totalPaymentAmount) * 100, 2) : 0,
            ],
            'allocation_distribution' => [
                'fully_allocated' => $fullyAllocatedPayments,
                'partially_allocated' => $partiallyAllocatedPayments,
                'unallocated' => $unallocatedPayments,
            ],
            'strategy_usage' => $strategies->toArray(),
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Get balance impact projection for scenarios.
     */
    public function getBalanceImpactProjection(
        Company $company,
        array $scenarios
    ): array {
        $projections = [];

        foreach ($scenarios as $scenario) {
            $projection = $this->calculateScenarioImpact($company, $scenario);
            $projections[] = $projection;
        }

        return [
            'company_id' => $company->id,
            'generated_at' => now()->toISOString(),
            'scenarios' => $projections,
        ];
    }

    /**
     * Clear balance cache for specific customer or company.
     */
    public function clearBalanceCache(Company $company, ?string $customerId = null): void
    {
        if ($customerId) {
            Cache::forget("customer_balance_{$company->id}_{$customerId}");
        } else {
            // Clear all customer balances for company
            $customers = Customer::where('company_id', $company->id)->pluck('id');
            foreach ($customers as $id) {
                Cache::forget("customer_balance_{$company->id}_{$id}");
            }
        }

        Cache::forget("company_balance_overview_{$company->id}");
    }

    /**
     * Calculate customer balance at a specific point in time.
     */
    private function getBalanceAtDate(Company $company, string $customerId, \DateTime $date): array
    {
        $invoices = Invoice::where('company_id', $company->id)
            ->where('customer_id', $customerId)
            ->where('created_at', '<=', $date)
            ->where('status', '!=', 'cancelled')
            ->with(['activePaymentAllocations' => function ($query) use ($date) {
                $query->where('allocation_date', '<=', $date);
            }])
            ->get();

        $totalBalanceDue = 0;
        $totalAllocated = 0;
        $invoiceCount = 0;

        foreach ($invoices as $invoice) {
            if ($invoice->status !== 'paid') {
                $totalBalanceDue += $invoice->total_amount;
                $totalAllocated += $invoice->total_allocated;
                $invoiceCount++;
            }
        }

        $unallocatedPayments = Payment::where('company_id', $company->id)
            ->where('customer_id', $customerId)
            ->where('created_at', '<=', $date)
            ->where('status', 'completed')
            ->get()
            ->sum('remaining_amount');

        return [
            'total_balance_due' => $totalBalanceDue,
            'total_allocated' => $totalAllocated,
            'unallocated_amount' => $unallocatedPayments,
            'net_balance' => $totalBalanceDue - $totalAllocated - $unallocatedPayments,
            'invoice_count' => $invoiceCount,
            'payment_count' => Payment::where('company_id', $company->id)
                ->where('customer_id', $customerId)
                ->where('created_at', '<=', $date)
                ->count(),
        ];
    }

    /**
     * Calculate customer balance.
     */
    private function calculateCustomerBalance(Company $company, string $customerId): array
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
            'updated_at' => now()->toISOString(),
        ];
    }

    /**
     * Get invoice statistics.
     */
    private function getInvoiceStatistics(Company $company): array
    {
        return [
            'total' => Invoice::where('company_id', $company->id)->count(),
            'draft' => Invoice::where('company_id', $company->id)->where('status', 'draft')->count(),
            'sent' => Invoice::where('company_id', $company->id)->where('status', 'sent')->count(),
            'paid' => Invoice::where('company_id', $company->id)->where('status', 'paid')->count(),
            'overdue' => Invoice::where('company_id', $company->id)->overdue()->count(),
        ];
    }

    /**
     * Get payment statistics.
     */
    private function getPaymentStatistics(Company $company): array
    {
        return [
            'total' => Payment::where('company_id', $company->id)->count(),
            'pending' => Payment::where('company_id', $company->id)->where('status', 'pending')->count(),
            'completed' => Payment::where('company_id', $company->id)->where('status', 'completed')->count(),
            'failed' => Payment::where('company_id', $company->id)->where('status', 'failed')->count(),
            'total_amount' => Payment::where('company_id', $company->id)->sum('amount'),
        ];
    }

    /**
     * Get aging summary.
     */
    private function getAgingSummary(Company $company): array
    {
        $agingReport = $this->getAgingReport($company);
        
        return [
            'current' => $agingReport['buckets']['current']['amount'],
            '1_30_days' => $agingReport['buckets']['1_30_days']['amount'],
            '31_60_days' => $agingReport['buckets']['31_60_days']['amount'],
            '61_90_days' => $agingReport['buckets']['61_90_days']['amount'],
            'over_90_days' => $agingReport['buckets']['over_90_days']['amount'],
        ];
    }

    /**
     * Determine aging bucket based on days overdue.
     */
    private function getAgingBucket(int $daysOverdue): string
    {
        if ($daysOverdue <= 0) return 'current';
        if ($daysOverdue <= 30) return '1_30_days';
        if ($daysOverdue <= 60) return '31_60_days';
        if ($daysOverdue <= 90) return '61_90_days';
        return 'over_90_days';
    }

    /**
     * Generate date periods for balance history.
     */
    private function generateDatePeriods(\DateTime $startDate, \DateTime $endDate, string $period): array
    {
        $periods = [];
        $current = clone $startDate;

        switch ($period) {
            case 'daily':
                while ($current <= $endDate) {
                    $periods[] = [
                        'date' => clone $current,
                        'start' => clone $current,
                        'end' => clone $current,
                    ];
                    $current->addDay();
                }
                break;
            case 'weekly':
                while ($current <= $endDate) {
                    $weekEnd = clone $current;
                    $weekEnd->addDays(6)->min($endDate);
                    
                    $periods[] = [
                        'date' => clone $current,
                        'start' => clone $current,
                        'end' => clone $weekEnd,
                    ];
                    $current->addDays(7);
                }
                break;
            case 'monthly':
                while ($current <= $endDate) {
                    $monthEnd = clone $current;
                    $monthEnd->endOfMonth()->min($endDate);
                    
                    $periods[] = [
                        'date' => clone $current,
                        'start' => clone $current,
                        'end' => clone $monthEnd,
                    ];
                    $current->addMonth();
                }
                break;
        }

        return $periods;
    }

    /**
     * Calculate scenario impact.
     */
    private function calculateScenarioImpact(Company $company, array $scenario): array
    {
        // Implementation for scenario projection would go here
        // This could include projections for payment receipts, invoice payments, etc.
        return [
            'name' => $scenario['name'] ?? 'Unknown Scenario',
            'description' => $scenario['description'] ?? '',
            'impact' => $scenario['impact'] ?? [],
        ];
    }
}
