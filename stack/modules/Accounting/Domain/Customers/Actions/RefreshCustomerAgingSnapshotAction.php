<?php

namespace Modules\Accounting\Domain\Customers\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Modules\Accounting\Domain\Customers\Events\AgingSnapshotRefreshed;
use Modules\Accounting\Domain\Customers\Models\Customer;
use Modules\Accounting\Domain\Customers\Models\CustomerAgingSnapshot;
use Modules\Accounting\Domain\Customers\Services\CustomerAgingService;

class RefreshCustomerAgingSnapshotAction
{
    public function __construct(
        private CustomerAgingService $agingService
    ) {}

    /**
     * Refresh aging snapshot for a specific customer.
     */
    public function execute(
        Customer $customer,
        ?Carbon $snapshotDate = null,
        string $generatedVia = 'on_demand',
        ?string $generatedByUserId = null
    ): CustomerAgingSnapshot {
        $snapshotDate = $snapshotDate ?? now()->startOfDay();

        return DB::transaction(function () use ($customer, $snapshotDate, $generatedVia, $generatedByUserId) {
            // Create or update the aging snapshot
            $snapshot = $this->agingService->createSnapshot(
                $customer,
                $snapshotDate,
                $generatedVia,
                $generatedByUserId
            );

            // Fire event
            Event::dispatch(new AgingSnapshotRefreshed($snapshot, $customer, [
                'generated_via' => $generatedVia,
                'generated_by_user_id' => $generatedByUserId,
            ]));

            Log::info('Customer aging snapshot refreshed', [
                'snapshot_id' => $snapshot->id,
                'customer_id' => $customer->id,
                'company_id' => $customer->company_id,
                'snapshot_date' => $snapshotDate->format('Y-m-d'),
                'generated_via' => $generatedVia,
                'generated_by' => $generatedByUserId ?? 'system',
            ]);

            return $snapshot;
        });
    }

    /**
     * Refresh aging snapshots for multiple customers.
     */
    public function batchRefresh(
        array $customerIds,
        ?Carbon $snapshotDate = null,
        string $generatedVia = 'scheduled',
        ?string $generatedByUserId = null
    ): array {
        $snapshotDate = $snapshotDate ?? now()->startOfDay();
        $results = [
            'refreshed' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        foreach ($customerIds as $customerId) {
            try {
                $customer = Customer::where('id', $customerId)
                    ->where('company_id', $this->getCompanyIdFromContext())
                    ->firstOrFail();

                // Check if snapshot already exists for today
                $existing = CustomerAgingSnapshot::where('customer_id', $customerId)
                    ->where('snapshot_date', $snapshotDate->format('Y-m-d'))
                    ->first();

                if ($existing && ! $this->shouldRefreshExisting($existing)) {
                    $results['skipped']++;

                    continue;
                }

                $this->execute($customer, $snapshotDate, $generatedVia, $generatedByUserId);
                $results['refreshed']++;

            } catch (\Exception $e) {
                $results['errors'][] = [
                    'customer_id' => $customerId,
                    'error' => $e->getMessage(),
                ];

                Log::error('Failed to refresh aging snapshot in batch', [
                    'customer_id' => $customerId,
                    'snapshot_date' => $snapshotDate->format('Y-m-d'),
                    'generated_via' => $generatedVia,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Refresh aging snapshots for all customers in a company.
     */
    public function refreshAllForCompany(
        $companyId,
        ?Carbon $snapshotDate = null,
        string $generatedVia = 'scheduled',
        ?string $generatedByUserId = null
    ): array {
        return $this->batchCreateSnapshotsForCompany($companyId, $snapshotDate, $generatedVia, $generatedByUserId);
    }

    /**
     * Batch create aging snapshots for all customers in a company.
     */
    private function batchCreateSnapshotsForCompany(
        $companyId,
        ?Carbon $snapshotDate,
        string $generatedVia,
        ?string $generatedByUserId
    ): array {
        $snapshotDate = $snapshotDate ?? now()->startOfDay();

        return $this->agingService->batchCreateSnapshots(
            $companyId,
            $snapshotDate,
            $generatedVia,
            $generatedByUserId
        );
    }

    /**
     * Refresh aging snapshots with trend analysis.
     */
    public function refreshWithTrend(
        Customer $customer,
        int $daysToInclude = 90,
        string $generatedVia = 'on_demand',
        ?string $generatedByUserId = null
    ): array {
        $results = [];
        $currentDate = now()->startOfDay();

        for ($i = 0; $i < $daysToInclude; $i++) {
            $snapshotDate = $currentDate->copy()->subDays($i);

            try {
                $snapshot = $this->execute($customer, $snapshotDate, $generatedVia, $generatedByUserId);
                $results[] = $snapshot;
            } catch (\Exception $e) {
                Log::warning('Failed to refresh historical aging snapshot', [
                    'customer_id' => $customer->id,
                    'snapshot_date' => $snapshotDate->format('Y-m-d'),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Get aging performance metrics for a company.
     */
    public function getPerformanceMetrics($companyId, Carbon $startDate, Carbon $endDate): array
    {
        $snapshots = CustomerAgingSnapshot::where('company_id', $companyId)
            ->whereBetween('snapshot_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->orderBy('snapshot_date')
            ->get();

        if ($snapshots->isEmpty()) {
            return [
                'total_snapshots' => 0,
                'average_outstanding' => 0,
                'peak_outstanding' => 0,
                'trend_direction' => 'stable',
                'riskiest_day' => null,
                'riskiest_amount' => 0,
            ];
        }

        $totalOutstanding = $snapshots->sum(function ($snapshot) {
            return $snapshot->bucket_current + $snapshot->bucket_1_30 +
                   $snapshot->bucket_31_60 + $snapshot->bucket_61_90 + $snapshot->bucket_90_plus;
        });

        $averageOutstanding = $totalOutstanding / $snapshots->count();
        $peakOutstanding = $snapshots->max(function ($snapshot) {
            return $snapshot->bucket_current + $snapshot->bucket_1_30 +
                   $snapshot->bucket_31_60 + $snapshot->bucket_61_90 + $snapshot->bucket_90_plus;
        });

        // Calculate trend direction
        $trendDirection = $this->calculateTrendDirection($snapshots);

        // Find riskiest day (highest 90+ bucket)
        $riskiestSnapshot = $snapshots->max('bucket_90_plus');
        $riskiestDay = $snapshots->firstWhere('bucket_90_plus', $riskiestSnapshot);
        $riskiestAmount = $riskiestSnapshot ? (float) $riskiestSnapshot : 0;

        return [
            'total_snapshots' => $snapshots->count(),
            'average_outstanding' => (float) $averageOutstanding,
            'peak_outstanding' => (float) $peakOutstanding,
            'trend_direction' => $trendDirection,
            'riskiest_day' => $riskiestDay?->snapshot_date->format('Y-m-d'),
            'riskiest_amount' => $riskiestAmount,
            'period_start' => $startDate->format('Y-m-d'),
            'period_end' => $endDate->format('Y-m-d'),
        ];
    }

    /**
     * Calculate trend direction based on aging snapshots.
     */
    private function calculateTrendDirection($snapshots): string
    {
        if ($snapshots->count() < 2) {
            return 'stable';
        }

        $firstHalf = $snapshots->take(floor($snapshots->count() / 2));
        $secondHalf = $snapshots->skip(floor($snapshots->count() / 2));

        $firstHalfAverage = $firstHalf->avg(function ($snapshot) {
            return $snapshot->bucket_current + $snapshot->bucket_1_30 +
                   $snapshot->bucket_31_60 + $snapshot->bucket_61_90 + $snapshot->bucket_90_plus;
        });

        $secondHalfAverage = $secondHalf->avg(function ($snapshot) {
            return $snapshot->bucket_current + $snapshot->bucket_1_30 +
                   $snapshot->bucket_31_60 + $snapshot->bucket_61_90 + $snapshot->bucket_90_plus;
        });

        $difference = $secondHalfAverage - $firstHalfAverage;
        $threshold = $firstHalfAverage * 0.05; // 5% threshold

        if (abs($difference) < $threshold) {
            return 'stable';
        }

        return $difference > 0 ? 'increasing' : 'decreasing';
    }

    /**
     * Determine if existing snapshot should be refreshed.
     */
    private function shouldRefreshExisting(CustomerAgingSnapshot $existing): bool
    {
        // Always refresh if the snapshot is more than 1 hour old
        if ($existing->created_at->lt(now()->subHour())) {
            return true;
        }

        // Refresh if it was generated on-demand and we're now doing scheduled refresh
        if ($existing->generated_via === 'on_demand') {
            return true;
        }

        return false;
    }

    /**
     * Get company ID from context (this would need to be implemented based on your context system).
     */
    private function getCompanyIdFromContext(): ?string
    {
        // This would typically come from request context, tenant context, etc.
        // For now, return null and let the caller handle it
        return null;
    }

    /**
     * Purge old aging snapshots for a company.
     */
    public function purgeOldSnapshots($companyId, int $retentionDays = 730): int
    {
        return $this->agingService->purgeOldSnapshots($companyId, $retentionDays);
    }

    /**
     * Get aging health score for a customer.
     */
    public function getAgingHealthScore(Customer $customer, ?Carbon $asOfDate = null): array
    {
        $asOfDate = $asOfDate ?? now();
        $buckets = $this->agingService->calculateAgingBuckets($customer, $asOfDate);

        $totalOutstanding = $buckets['bucket_current'] + $buckets['bucket_1_30'] +
                          $buckets['bucket_31_60'] + $buckets['bucket_61_90'] + $buckets['bucket_90_plus'];

        if ($totalOutstanding == 0) {
            return [
                'score' => 100,
                'grade' => 'A+',
                'risk_level' => 'none',
                'factors' => ['No outstanding balance'],
            ];
        }

        // Calculate weighted risk score
        $weights = [
            'current' => 0,
            'bucket_1_30' => 10,
            'bucket_31_60' => 30,
            'bucket_61_90' => 60,
            'bucket_90_plus' => 100,
        ];

        $riskScore = 0;
        foreach ($weights as $bucket => $weight) {
            $bucketAmount = $buckets[$bucket] ?? 0;
            $bucketPercentage = $totalOutstanding > 0 ? ($bucketAmount / $totalOutstanding) * 100 : 0;
            $riskScore += ($bucketPercentage / 100) * $weight;
        }

        // Convert to health score (100 - riskScore)
        $healthScore = max(0, 100 - $riskScore);

        // Determine grade
        $grade = match (true) {
            $healthScore >= 90 => 'A+',
            $healthScore >= 80 => 'A',
            $healthScore >= 70 => 'B',
            $healthScore >= 60 => 'C',
            $healthScore >= 50 => 'D',
            default => 'F'
        };

        // Determine risk level
        $riskLevel = match (true) {
            $healthScore >= 80 => 'low',
            $healthScore >= 60 => 'moderate',
            $healthScore >= 40 => 'high',
            default => 'critical'
        };

        // Identify risk factors
        $riskFactors = [];
        if ($buckets['bucket_90_plus'] > 0) {
            $riskFactors[] = 'Severely overdue invoices (90+ days)';
        }
        if ($buckets['bucket_61_90'] > 0) {
            $riskFactors[] = 'Significantly overdue invoices (61-90 days)';
        }
        if ($buckets['bucket_31_60'] > $totalOutstanding * 0.3) {
            $riskFactors[] = 'High proportion in 31-60 day bucket';
        }

        return [
            'score' => round($healthScore, 1),
            'grade' => $grade,
            'risk_level' => $riskLevel,
            'factors' => $riskFactors,
            'total_outstanding' => $totalOutstanding,
            'buckets' => $buckets,
            'as_of_date' => $asOfDate->format('Y-m-d'),
        ];
    }
}
