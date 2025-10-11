<?php

namespace Modules\Accounting\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class RedisCacheService
{
    /**
     * Get tenant-aware cache key.
     */
    public function getTenantKey(string $key, ?string $companyId = null): string
    {
        // If no company ID provided, try to get it from application context
        if (! $companyId) {
            $companyId = app()->bound('current_company_id')
                ? app('current_company_id')
                : null;
        }

        // If still no company ID, we cannot provide tenant isolation
        // This should throw an error to prevent cross-tenant data leakage
        if (! $companyId) {
            throw new \InvalidArgumentException(
                'Company ID is required for cache operations. Please provide a company ID or ensure a company context is set.'
            );
        }

        return "company:{$companyId}:{$key}";
    }

    /**
     * Remember data with company isolation.
     *
     * @param  \DateTimeInterface|\DateInterval|int|null  $ttl
     * @return mixed
     */
    public function rememberCompany(string $key, $ttl, \Closure $callback, ?string $companyId = null)
    {
        $tenantKey = $this->getTenantKey($key, $companyId);
        $connection = config('accounting_redis.connections.cache');

        return Cache::store('accounting_cache')->remember($tenantKey, $ttl, $callback);
    }

    /**
     * Remember data forever with company isolation.
     *
     * @return mixed
     */
    public function rememberCompanyForever(string $key, \Closure $callback, ?string $companyId = null)
    {
        $tenantKey = $this->getTenantKey($key, $companyId);

        return Cache::store('accounting_cache')->rememberForever($tenantKey, $callback);
    }

    /**
     * Forget data with company isolation.
     */
    public function forgetCompany(string $key, ?string $companyId = null): bool
    {
        $tenantKey = $this->getTenantKey($key, $companyId);

        return Cache::store('accounting_cache')->forget($tenantKey);
    }

    /**
     * Clear all cache for a specific company.
     */
    public function clearCompanyCache(string $companyId): bool
    {
        $prefix = config('accounting_redis.connections.cache.prefix');
        $pattern = $prefix."company:{$companyId}:*";

        try {
            $redis = Redis::connection('accounting_cache');
            $keys = $redis->keys($pattern);

            if (! empty($keys)) {
                $redis->del($keys);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to clear company cache', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Cache account balances for a company.
     */
    public function cacheAccountBalances(string $companyId, array $balances, ?string $periodId = null): void
    {
        $key = 'account_balances'.($periodId ? ":period:{$periodId}" : '');
        $ttl = config('accounting_redis.ttl.account_balances', 3600);

        $this->rememberCompany($key, $ttl, function () use ($balances) {
            return $balances;
        }, $companyId);
    }

    /**
     * Get cached account balances.
     */
    public function getCachedAccountBalances(string $companyId, ?string $periodId = null): ?array
    {
        $key = 'account_balances'.($periodId ? ":period:{$periodId}" : '');

        return Cache::store('accounting_cache')->get($this->getTenantKey($key, $companyId));
    }

    /**
     * Cache trial balance for a company.
     */
    public function cacheTrialBalance(string $companyId, array $trialBalance, ?string $periodId = null): void
    {
        $key = 'trial_balance'.($periodId ? ":period:{$periodId}" : '');
        $ttl = config('accounting_redis.ttl.trial_balance', 1800);

        $this->rememberCompany($key, $ttl, function () use ($trialBalance) {
            return $trialBalance;
        }, $companyId);
    }

    /**
     * Cache chart of accounts for a company.
     */
    public function cacheChartOfAccounts(string $companyId, array $chartOfAccounts): void
    {
        $key = 'chart_of_accounts';
        $ttl = config('accounting_redis.ttl.chart_of_accounts', 86400);

        $this->rememberCompany($key, $ttl, function () use ($chartOfAccounts) {
            return $chartOfAccounts;
        }, $companyId);
    }

    /**
     * Invalidate chart of accounts cache for a company.
     */
    public function invalidateChartOfAccounts(string $companyId): void
    {
        $this->forgetCompany('chart_of_accounts', $companyId);
        $this->forgetCompany('account_balances', $companyId);
        $this->forgetCompany('trial_balance', $companyId);
    }

    /**
     * Cache user permissions for a company.
     */
    public function cacheUserPermissions(string $userId, string $companyId, array $permissions): void
    {
        $key = "user_permissions:{$userId}";
        $ttl = config('accounting_redis.ttl.user_permissions', 3600);

        $this->rememberCompany($key, $ttl, function () use ($permissions) {
            return $permissions;
        }, $companyId);
    }

    /**
     * Get cached user permissions.
     */
    public function getCachedUserPermissions(string $userId, string $companyId): ?array
    {
        $key = "user_permissions:{$userId}";

        return Cache::store('accounting_cache')->get($this->getTenantKey($key, $companyId));
    }

    /**
     * Store a lock for preventing duplicate operations.
     */
    public function acquireLock(string $key, int $ttl = 60, ?string $companyId = null): bool
    {
        $tenantKey = $this->getTenantKey("lock:{$key}", $companyId);

        try {
            $redis = Redis::connection('accounting_locks');

            return $redis->set($tenantKey, '1', 'EX', $ttl, 'NX');
        } catch (\Exception $e) {
            Log::error('Failed to acquire lock', [
                'key' => $tenantKey,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Release a lock.
     */
    public function releaseLock(string $key, ?string $companyId = null): bool
    {
        $tenantKey = $this->getTenantKey("lock:{$key}", $companyId);

        try {
            $redis = Redis::connection('accounting_locks');

            return $redis->del($tenantKey) > 0;
        } catch (\Exception $e) {
            Log::error('Failed to release lock', [
                'key' => $tenantKey,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Queue an accounting job with company context.
     *
     * @param  \Illuminate\Contracts\Queue\ShouldQueue  $job
     * @return mixed
     */
    public function queueJob(string $queueName, $job, string $companyId)
    {
        $queue = config("accounting_redis.queues.{$queueName}", 'default');

        // Add company context to job
        if (method_exists($job, 'setCompanyId')) {
            $job->setCompanyId($companyId);
        }

        return dispatch($job)->onConnection('accounting')->onQueue($queue);
    }

    /**
     * Cache a financial report.
     */
    public function cacheFinancialReport(
        string $companyId,
        string $reportType,
        array $reportData,
        array $parameters = []
    ): void {
        $key = "report:{$reportType}";

        if (! empty($parameters)) {
            $key .= ':'.md5(serialize($parameters));
        }

        $ttl = config('accounting_redis.ttl.financial_reports', 86400);

        $this->rememberCompany($key, $ttl, function () use ($reportData) {
            return $reportData;
        }, $companyId);
    }

    /**
     * Get a cached financial report.
     */
    public function getCachedFinancialReport(
        string $companyId,
        string $reportType,
        array $parameters = []
    ): ?array {
        $key = "report:{$reportType}";

        if (! empty($parameters)) {
            $key .= ':'.md5(serialize($parameters));
        }

        return Cache::store('accounting_reports')->get($this->getTenantKey($key, $companyId));
    }

    /**
     * Set the current company context for cache operations.
     *
     * This method should be called in queue jobs or CLI commands
     * to ensure proper tenant isolation.
     */
    public function setCompanyContext(string $companyId): void
    {
        app()->instance('current_company_id', $companyId);
    }

    /**
     * Execute a callback with company context.
     *
     * @return mixed
     */
    public function withCompanyContext(string $companyId, \Closure $callback)
    {
        $previousCompanyId = app()->bound('current_company_id')
            ? app('current_company_id')
            : null;

        $this->setCompanyContext($companyId);

        try {
            return $callback();
        } finally {
            // Restore previous context
            if ($previousCompanyId) {
                app()->instance('current_company_id', $previousCompanyId);
            } else {
                app()->forgetInstance('current_company_id');
            }
        }
    }
}
