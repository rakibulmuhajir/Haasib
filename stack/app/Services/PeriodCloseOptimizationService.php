<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Company;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PeriodCloseOptimizationService
{
    /**
     * Optimize period close operations for better resource management.
     */
    public function optimizeForPeriodClose(Company $company): void
    {
        // Clear caches to free memory
        $this->clearCompanyCaches($company->id);

        // Optimize database connections
        $this->optimizeDatabaseConnection();

        // Preload frequently accessed data in bulk
        $this->preloadPeriodData($company);
    }

    /**
     * Clear company-specific caches to free memory.
     */
    private function clearCompanyCaches(string $companyId): void
    {
        $cacheKeys = [
            "company_balance_{$companyId}",
            "customer_balances_{$companyId}",
            "vendor_balances_{$companyId}",
            "accounts_{$companyId}",
            "period_status_{$companyId}",
        ];

        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }

        // Clear cache by pattern
        Cache::flush(); // Conservative approach for testing
    }

    /**
     * Optimize database connection settings for batch operations.
     */
    private function optimizeDatabaseConnection(): void
    {
        // Set appropriate isolation level for batch operations
        try {
            DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
        } catch (\Exception $e) {
            // Continue even if this fails
        }

        // Optimize memory usage for large queries (may fail with limited permissions)
        $statements = [
            'SET SESSION work_mem = \'16MB\'',
            'SET SESSION maintenance_work_mem = \'64MB\'',
        ];

        foreach ($statements as $statement) {
            try {
                DB::statement($statement);
            } catch (\Exception $e) {
                // Log and continue - these are optimizations, not requirements
                Log::warning("Database optimization failed: {$statement} - {$e->getMessage()}");
            }
        }
    }

    /**
     * Preload frequently accessed data efficiently.
     */
    private function preloadPeriodData(Company $company): void
    {
        // Load accounts with chunking to avoid memory spikes
        Account::where('company_id', $company->id)
            ->orderBy('account_number')
            ->chunk(100, function ($accounts) {
                // Process chunk without storing all in memory
                $accounts->each(function ($account) {
                    // Cache minimal required data
                    Cache::put(
                        "account_{$account->id}_balance",
                        $account->current_balance,
                        300 // 5 minutes
                    );
                });
            });
    }

    /**
     * Get memory usage statistics for monitoring.
     */
    public function getMemoryStats(): array
    {
        return [
            'current_usage' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_usage' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'limit' => ini_get('memory_limit'),
            'gc_collected' => gc_collect_cycles(),
        ];
    }

    /**
     * Force garbage collection if memory usage is high.
     */
    public function manageMemory(): void
    {
        $currentUsage = memory_get_usage(true);
        $threshold = 100 * 1024 * 1024; // 100MB threshold

        if ($currentUsage > $threshold) {
            $collected = gc_collect_cycles();
            Log::info("Garbage collection triggered: {$collected} cycles collected");
        }
    }

    /**
     * Create memory-efficient chunked processor for large datasets.
     */
    public function processInChunks($query, int $chunkSize, callable $processor): void
    {
        $query->chunk($chunkSize, function ($chunk) use ($processor) {
            $processor($chunk);

            // Manage memory after each chunk
            $this->manageMemory();

            // Clear chunk from memory
            unset($chunk);
        });
    }

    /**
     * Optimize database tables before period close.
     */
    public function optimizeTables(): void
    {
        // Update table statistics for better query planning
        DB::statement('ANALYZE acct.accounts');
        DB::statement('ANALYZE acct.journal_entries');
        DB::statement('ANALYZE acct.journal_lines');
        DB::statement('ANALYZE acct.payments');
        DB::statement('ANALYZE acct.payment_allocations');
    }

    /**
     * Reset database connection settings after period close.
     */
    public function resetOptimizations(): void
    {
        // Reset database settings to defaults
        $statements = [
            'SET SESSION TRANSACTION ISOLATION LEVEL DEFAULT',
            'RESET work_mem',
            'RESET maintenance_work_mem',
        ];

        foreach ($statements as $statement) {
            try {
                DB::statement($statement);
            } catch (\Exception $e) {
                // Log and continue - these are cleanup operations
                Log::warning("Database reset failed: {$statement} - {$e->getMessage()}");
            }
        }
    }
}
