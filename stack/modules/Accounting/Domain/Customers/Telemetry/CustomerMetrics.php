<?php

namespace Modules\Accounting\Domain\Customers\Telemetry;

use Illuminate\Support\Facades\Log;

class CustomerMetrics
{
    /**
     * Record customer creation metrics.
     */
    public static function customerCreated(string $companyId, string $source = 'manual'): void
    {
        Log::info('Customer created', [
            'company_id' => $companyId,
            'source' => $source,
            'metric_type' => 'customer_created',
            'timestamp' => now()->toISOString(),
        ]);

        // Additional metrics tracking can be added here
        // such as sending to monitoring services, analytics platforms, etc.
    }

    /**
     * Record customer update metrics.
     */
    public static function customerUpdated(string $companyId, array $changes): void
    {
        Log::info('Customer updated', [
            'company_id' => $companyId,
            'changes_count' => count($changes),
            'metric_type' => 'customer_updated',
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Record customer deletion metrics.
     */
    public static function customerDeleted(string $companyId, string $customerName): void
    {
        Log::info('Customer deleted', [
            'company_id' => $companyId,
            'metric_type' => 'customer_deleted',
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Record customer import metrics.
     */
    public static function customersImported(string $companyId, string $sourceType, int $totalCount, int $importedCount): void
    {
        Log::info('Customers imported', [
            'company_id' => $companyId,
            'source_type' => $sourceType,
            'total_count' => $totalCount,
            'imported_count' => $importedCount,
            'success_rate' => $totalCount > 0 ? ($importedCount / $totalCount) * 100 : 0,
            'metric_type' => 'customers_imported',
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Record customer export metrics.
     */
    public static function customersExported(string $companyId, string $format, int $count): void
    {
        Log::info('Customers exported', [
            'company_id' => $companyId,
            'format' => $format,
            'count' => $count,
            'metric_type' => 'customers_exported',
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Record aging snapshot creation metrics.
     */
    public static function agingSnapshotCreated(string $companyId, int $customerCount): void
    {
        Log::info('Aging snapshots created', [
            'company_id' => $companyId,
            'customer_count' => $customerCount,
            'metric_type' => 'aging_snapshot_created',
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Record statement generation metrics.
     */
    public static function statementGenerated(string $companyId, string $format, int $customerCount): void
    {
        Log::info('Customer statements generated', [
            'company_id' => $companyId,
            'format' => $format,
            'customer_count' => $customerCount,
            'metric_type' => 'statement_generated',
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Record customer lifecycle status change.
     */
    public static function customerStatusChanged(string $companyId, string $fromStatus, string $toStatus): void
    {
        Log::info('Customer status changed', [
            'company_id' => $companyId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'metric_type' => 'customer_status_changed',
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Record credit limit change metrics.
     */
    public static function creditLimitChanged(string $companyId, float $oldLimit, float $newLimit): void
    {
        Log::info('Customer credit limit changed', [
            'company_id' => $companyId,
            'old_limit' => $oldLimit,
            'new_limit' => $newLimit,
            'change_amount' => $newLimit - $oldLimit,
            'metric_type' => 'credit_limit_changed',
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Record customer balance-related metrics.
     */
    public static function customerBalanceMetrics(string $companyId, int $customerCount, float $totalBalance, float $averageBalance): void
    {
        Log::info('Customer balance metrics', [
            'company_id' => $companyId,
            'customer_count' => $customerCount,
            'total_balance' => $totalBalance,
            'average_balance' => $averageBalance,
            'metric_type' => 'customer_balance_metrics',
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Record customer search and filtering metrics.
     */
    public static function customerSearchPerformed(string $companyId, array $filters, int $resultCount): void
    {
        Log::info('Customer search performed', [
            'company_id' => $companyId,
            'filters' => $filters,
            'result_count' => $resultCount,
            'metric_type' => 'customer_search_performed',
            'timestamp' => now()->toISOString(),
        ]);
    }
}
