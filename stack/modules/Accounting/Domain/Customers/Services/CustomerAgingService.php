<?php

namespace Modules\Accounting\Domain\Customers\Services;

use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Accounting\Domain\Customers\Models\Customer;
use Modules\Accounting\Domain\Customers\Models\CustomerAgingSnapshot;

class CustomerAgingService
{
    /**
     * Calculate aging buckets for a customer as of a specific date.
     */
    public function calculateAgingBuckets(Customer $customer, ?Carbon $asOfDate = null): array
    {
        $asOfDate = $asOfDate ?? now();

        // Get all invoices for the customer
        $invoices = Invoice::where('customer_id', $customer->id)
            ->where('company_id', $customer->company_id)
            ->where('status', '!=', 'draft')
            ->where('issue_date', '<=', $asOfDate)
            ->get();

        // Initialize buckets
        $buckets = [
            'bucket_current' => 0.0,
            'bucket_1_30' => 0.0,
            'bucket_31_60' => 0.0,
            'bucket_61_90' => 0.0,
            'bucket_90_plus' => 0.0,
            'total_outstanding' => 0.0,
            'total_invoices' => 0,
            'currency' => $customer->default_currency,
            'as_of_date' => $asOfDate->format('Y-m-d'),
        ];

        foreach ($invoices as $invoice) {
            // Skip fully paid invoices
            if ($invoice->balance_due <= 0) {
                continue;
            }

            $buckets['total_invoices']++;
            $buckets['total_outstanding'] += $invoice->balance_due;

            // Determine which bucket this invoice belongs to
            $bucket = $this->getAgingBucket($invoice, $asOfDate);
            $buckets[$bucket] += $invoice->balance_due;
        }

        return $buckets;
    }

    /**
     * Get aging bucket for a specific invoice.
     */
    private function getAgingBucket(Invoice $invoice, Carbon $asOfDate): string
    {
        // If not yet due, put in current bucket
        if ($invoice->due_date->isFuture($asOfDate)) {
            return 'bucket_current';
        }

        $daysOverdue = $asOfDate->diffInDays($invoice->due_date);

        return match (true) {
            $daysOverdue <= 30 => 'bucket_1_30',
            $daysOverdue <= 60 => 'bucket_31_60',
            $daysOverdue <= 90 => 'bucket_61_90',
            default => 'bucket_90_plus'
        };
    }

    /**
     * Create aging snapshot for a customer.
     */
    public function createSnapshot(Customer $customer, ?Carbon $snapshotDate = null, string $generatedVia = 'on_demand', ?string $generatedByUserId = null): CustomerAgingSnapshot
    {
        $snapshotDate = $snapshotDate ?? now()->startOfDay();

        // Check if snapshot already exists for this date
        $existing = CustomerAgingSnapshot::where('customer_id', $customer->id)
            ->where('snapshot_date', $snapshotDate)
            ->first();

        if ($existing) {
            Log::info('Aging snapshot already exists', [
                'customer_id' => $customer->id,
                'snapshot_date' => $snapshotDate->format('Y-m-d'),
                'existing_id' => $existing->id,
            ]);

            return $existing;
        }

        // Calculate aging buckets
        $buckets = $this->calculateAgingBuckets($customer, $snapshotDate);

        // Create snapshot
        return CustomerAgingSnapshot::create([
            'customer_id' => $customer->id,
            'company_id' => $customer->company_id,
            'snapshot_date' => $snapshotDate,
            'bucket_current' => $buckets['bucket_current'],
            'bucket_1_30' => $buckets['bucket_1_30'],
            'bucket_31_60' => $buckets['bucket_31_60'],
            'bucket_61_90' => $buckets['bucket_61_90'],
            'bucket_90_plus' => $buckets['bucket_90_plus'],
            'total_invoices' => $buckets['total_invoices'],
            'generated_via' => $generatedVia,
            'generated_by_user_id' => $generatedByUserId,
        ]);
    }

    /**
     * Get aging history for a customer.
     */
    public function getAgingHistory(Customer $customer, int $limit = 12, ?Carbon $startDate = null): Collection
    {
        $query = CustomerAgingSnapshot::where('customer_id', $customer->id)
            ->where('company_id', $customer->company_id)
            ->orderBy('snapshot_date', 'desc')
            ->limit($limit);

        if ($startDate) {
            $query->where('snapshot_date', '>=', $startDate);
        }

        return $query->get();
    }

    /**
     * Get aging trend data for chart display.
     */
    public function getAgingTrend(Customer $customer, int $days = 90): array
    {
        $snapshots = CustomerAgingSnapshot::where('customer_id', $customer->id)
            ->where('company_id', $customer->company_id)
            ->where('snapshot_date', '>=', now()->subDays($days))
            ->orderBy('snapshot_date', 'asc')
            ->get();

        $trend = [
            'dates' => [],
            'current' => [],
            '1_30' => [],
            '31_60' => [],
            '61_90' => [],
            '90_plus' => [],
            'total_outstanding' => [],
        ];

        foreach ($snapshots as $snapshot) {
            $trend['dates'][] = $snapshot->snapshot_date->format('Y-m-d');
            $trend['current'][] = (float) $snapshot->bucket_current;
            $trend['1_30'][] = (float) $snapshot->bucket_1_30;
            $trend['31_60'][] = (float) $snapshot->bucket_31_60;
            $trend['61_90'][] = (float) $snapshot->bucket_61_90;
            $trend['90_plus'][] = (float) $snapshot->bucket_90_plus;

            $totalOutstanding = $snapshot->bucket_current + $snapshot->bucket_1_30 +
                              $snapshot->bucket_31_60 + $snapshot->bucket_61_90 + $snapshot->bucket_90_plus;
            $trend['total_outstanding'][] = (float) $totalOutstanding;
        }

        return $trend;
    }

    /**
     * Get customers with high aging balances for a company.
     */
    public function getHighRiskCustomers($companyId, float $threshold = 5000.00, int $limit = 50): Collection
    {
        return CustomerAgingSnapshot::with(['customer'])
            ->where('company_id', $companyId)
            ->where('snapshot_date', now()->subDays(1)->toDateString()) // Yesterday's snapshot
            ->whereRaw('(bucket_31_60 + bucket_61_90 + bucket_90_plus) > ?', [$threshold])
            ->orderByRaw('(bucket_31_60 + bucket_61_90 + bucket_90_plus) DESC')
            ->limit($limit)
            ->get();
    }

    /**
     * Get company-wide aging summary.
     */
    public function getCompanyAgingSummary($companyId, ?Carbon $asOfDate = null): array
    {
        $asOfDate = $asOfDate ?? now()->subDay()->startOfDay();

        $summary = DB::table('acct.customer_aging_snapshots')
            ->where('company_id', $companyId)
            ->where('snapshot_date', $asOfDate->toDateString())
            ->selectRaw('
                COUNT(*) as total_customers,
                SUM(bucket_current) as total_current,
                SUM(bucket_1_30) as total_1_30,
                SUM(bucket_31_60) as total_31_60,
                SUM(bucket_61_90) as total_61_90,
                SUM(bucket_90_plus) as total_90_plus,
                SUM(total_invoices) as total_invoices,
                SUM(bucket_current + bucket_1_30 + bucket_31_60 + bucket_61_90 + bucket_90_plus) as total_outstanding
            ')
            ->first();

        return [
            'as_of_date' => $asOfDate->format('Y-m-d'),
            'total_customers' => (int) ($summary->total_customers ?? 0),
            'total_outstanding' => (float) ($summary->total_outstanding ?? 0),
            'total_invoices' => (int) ($summary->total_invoices ?? 0),
            'buckets' => [
                'current' => (float) ($summary->total_current ?? 0),
                '1_30' => (float) ($summary->total_1_30 ?? 0),
                '31_60' => (float) ($summary->total_31_60 ?? 0),
                '61_90' => (float) ($summary->total_61_90 ?? 0),
                '90_plus' => (float) ($summary->total_90_plus ?? 0),
            ],
            'risk_distribution' => $this->calculateRiskDistribution($companyId, $asOfDate),
        ];
    }

    /**
     * Calculate risk distribution for aging analysis.
     */
    private function calculateRiskDistribution($companyId, Carbon $asOfDate): array
    {
        $distribution = DB::table('acct.customer_aging_snapshots')
            ->where('company_id', $companyId)
            ->where('snapshot_date', $asOfDate->toDateString())
            ->selectRaw('
                COUNT(CASE WHEN bucket_90_plus > 0 THEN 1 END) as high_risk_customers,
                COUNT(CASE WHEN bucket_61_90 > 0 OR bucket_90_plus > 0 THEN 1 END) as elevated_risk_customers,
                COUNT(CASE WHEN bucket_31_60 > 0 AND bucket_61_90 = 0 AND bucket_90_plus = 0 THEN 1 END) as moderate_risk_customers,
                COUNT(CASE WHEN bucket_current > 0 OR bucket_1_30 > 0 AND bucket_31_60 = 0 AND bucket_61_90 = 0 AND bucket_90_plus = 0 THEN 1 END) as low_risk_customers,
                COUNT(CASE WHEN bucket_current + bucket_1_30 + bucket_31_60 + bucket_61_90 + bucket_90_plus = 0 THEN 1 END) as no_balance_customers
            ')
            ->first();

        return [
            'high_risk' => (int) ($distribution->high_risk_customers ?? 0),     // 90+ days overdue
            'elevated_risk' => (int) ($distribution->elevated_risk_customers ?? 0), // 61+ days overdue
            'moderate_risk' => (int) ($distribution->moderate_risk_customers ?? 0), // 31-60 days overdue
            'low_risk' => (int) ($distribution->low_risk_customers ?? 0),       // Current or 1-30 days
            'no_balance' => (int) ($distribution->no_balance_customers ?? 0),     // No outstanding balance
        ];
    }

    /**
     * Batch create aging snapshots for multiple customers.
     */
    public function batchCreateSnapshots($companyId, ?Carbon $snapshotDate = null, string $generatedVia = 'scheduled', ?string $generatedByUserId = null): array
    {
        $snapshotDate = $snapshotDate ?? now()->startOfDay();
        $results = ['created' => 0, 'skipped' => 0, 'errors' => []];

        // Get all customers for the company
        $customers = Customer::where('company_id', $companyId)->get();

        foreach ($customers as $customer) {
            try {
                $snapshot = $this->createSnapshot($customer, $snapshotDate, $generatedVia, $generatedByUserId);

                if ($snapshot->wasRecentlyCreated) {
                    $results['created']++;
                } else {
                    $results['skipped']++;
                }
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'customer_id' => $customer->id,
                    'error' => $e->getMessage(),
                ];

                Log::error('Failed to create aging snapshot', [
                    'customer_id' => $customer->id,
                    'company_id' => $companyId,
                    'snapshot_date' => $snapshotDate->format('Y-m-d'),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Purge old aging snapshots beyond retention period.
     */
    public function purgeOldSnapshots($companyId, int $retentionDays = 730): int
    {
        $cutoffDate = now()->subDays($retentionDays)->startOfDay();

        $deleted = CustomerAgingSnapshot::where('company_id', $companyId)
            ->where('snapshot_date', '<', $cutoffDate)
            ->delete();

        Log::info('Purged old aging snapshots', [
            'company_id' => $companyId,
            'cutoff_date' => $cutoffDate->format('Y-m-d'),
            'deleted_count' => $deleted,
        ]);

        return $deleted;
    }
}
