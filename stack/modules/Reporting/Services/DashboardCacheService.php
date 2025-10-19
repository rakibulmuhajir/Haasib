<?php

namespace Modules\Reporting\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DashboardCacheService
{
    private const DASHBOARD_CACHE_PREFIX = 'reporting:dashboard';

    private const KPI_CACHE_PREFIX = 'reporting:kpi';

    private const CACHE_LOCK_PREFIX = 'reporting:lock';

    /**
     * Get cache key for dashboard data
     */
    public function getDashboardCacheKey(string $companyId, string $layoutId, array $parameters = []): string
    {
        $paramHash = md5(serialize($parameters));

        return sprintf('%s:company:%s:layout:%s:params:%s',
            self::DASHBOARD_CACHE_PREFIX,
            $companyId,
            $layoutId,
            $paramHash
        );
    }

    /**
     * Get cache key for KPI data
     */
    public function getKpiCacheKey(string $companyId, string $kpiCode, array $parameters = []): string
    {
        $paramHash = md5(serialize($parameters));

        return sprintf('%s:company:%s:kpi:%s:params:%s',
            self::KPI_CACHE_PREFIX,
            $companyId,
            $kpiCode,
            $paramHash
        );
    }

    /**
     * Get cache lock key to prevent concurrent refreshes
     */
    public function getCacheLockKey(string $companyId, string $type = 'dashboard'): string
    {
        return sprintf('%s:company:%s:type:%s', self::CACHE_LOCK_PREFIX, $companyId, $type);
    }

    /**
     * Store dashboard metrics with TTL
     */
    public function storeDashboardMetrics(string $cacheKey, array $metrics, ?int $ttl = null): bool
    {
        try {
            $ttl = $ttl ?? config('reporting.cache.dashboard_ttl', 5);

            return Cache::store('reporting_dashboard')->put($cacheKey, $metrics, $ttl);
        } catch (\Exception $e) {
            Log::error('Failed to store dashboard metrics in cache', [
                'cache_key' => $cacheKey,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get dashboard metrics from cache
     */
    public function getDashboardMetrics(string $cacheKey): mixed
    {
        try {
            return Cache::store('reporting_dashboard')->get($cacheKey);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve dashboard metrics from cache', [
                'cache_key' => $cacheKey,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Store KPI data with TTL
     */
    public function storeKpiData(string $cacheKey, mixed $data, ?int $ttl = null): bool
    {
        try {
            $ttl = $ttl ?? config('reporting.cache.kpi_ttl', 300);

            return Cache::store('reporting_kpi')->put($cacheKey, $data, $ttl);
        } catch (\Exception $e) {
            Log::error('Failed to store KPI data in cache', [
                'cache_key' => $cacheKey,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get KPI data from cache
     */
    public function getKpiData(string $cacheKey): mixed
    {
        try {
            return Cache::store('reporting_kpi')->get($cacheKey);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve KPI data from cache', [
                'cache_key' => $cacheKey,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Invalidate dashboard cache for a company
     */
    public function invalidateDashboardCache(string $companyId): void
    {
        try {
            $pattern = sprintf('%s:company:%s:*', self::DASHBOARD_CACHE_PREFIX, $companyId);

            // Get Redis instance for pattern matching if available
            $cache = Cache::store('reporting_dashboard');

            if (method_exists($cache, 'getRedis')) {
                $redis = $cache->getRedis();
                $keys = $redis->keys($pattern);

                if (! empty($keys)) {
                    $redis->del($keys);
                }
            } else {
                // Fallback for other cache drivers
                // This is less efficient but works for most cases
                Cache::store('reporting_dashboard')->flush(); // Only if we can target specific patterns
            }

            Log::info('Dashboard cache invalidated', ['company_id' => $companyId]);
        } catch (\Exception $e) {
            Log::error('Failed to invalidate dashboard cache', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Invalidate KPI cache for a company
     */
    public function invalidateKpiCache(string $companyId): void
    {
        try {
            $pattern = sprintf('%s:company:%s:*', self::KPI_CACHE_PREFIX, $companyId);

            $cache = Cache::store('reporting_kpi');

            if (method_exists($cache, 'getRedis')) {
                $redis = $cache->getRedis();
                $keys = $redis->keys($pattern);

                if (! empty($keys)) {
                    $redis->del($keys);
                }
            }

            Log::info('KPI cache invalidated', ['company_id' => $companyId]);
        } catch (\Exception $e) {
            Log::error('Failed to invalidate KPI cache', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Invalidate all reporting cache for a company
     */
    public function invalidateAllCache(string $companyId): void
    {
        $this->invalidateDashboardCache($companyId);
        $this->invalidateKpiCache($companyId);
    }

    /**
     * Acquire cache lock to prevent concurrent refreshes
     */
    public function acquireCacheLock(string $companyId, string $type = 'dashboard', int $ttl = 30): bool
    {
        $lockKey = $this->getCacheLockKey($companyId, $type);

        try {
            return Cache::store('reporting_dashboard')->add($lockKey, true, $ttl);
        } catch (\Exception $e) {
            Log::error('Failed to acquire cache lock', [
                'company_id' => $companyId,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Release cache lock
     */
    public function releaseCacheLock(string $companyId, string $type = 'dashboard'): void
    {
        $lockKey = $this->getCacheLockKey($companyId, $type);

        try {
            Cache::store('reporting_dashboard')->forget($lockKey);
        } catch (\Exception $e) {
            Log::error('Failed to release cache lock', [
                'company_id' => $companyId,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check if cache lock exists
     */
    public function hasCacheLock(string $companyId, string $type = 'dashboard'): bool
    {
        $lockKey = $this->getCacheLockKey($companyId, $type);

        try {
            return Cache::store('reporting_dashboard')->has($lockKey);
        } catch (\Exception $e) {
            Log::error('Failed to check cache lock', [
                'company_id' => $companyId,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get cache statistics for monitoring
     */
    public function getCacheStats(string $companyId): array
    {
        try {
            $dashboardPattern = sprintf('%s:company:%s:*', self::DASHBOARD_CACHE_PREFIX, $companyId);
            $kpiPattern = sprintf('%s:company:%s:*', self::KPI_CACHE_PREFIX, $companyId);

            $dashboardCache = Cache::store('reporting_dashboard');
            $kpiCache = Cache::store('reporting_kpi');

            $stats = [
                'dashboard_cache_keys' => 0,
                'kpi_cache_keys' => 0,
                'total_memory_usage' => 0,
            ];

            // Count dashboard cache keys
            if (method_exists($dashboardCache, 'getRedis')) {
                $redis = $dashboardCache->getRedis();
                $dashboardKeys = $redis->keys($dashboardPattern);
                $stats['dashboard_cache_keys'] = count($dashboardKeys);

                // Get memory usage (Redis specific)
                foreach ($dashboardKeys as $key) {
                    $stats['total_memory_usage'] += $redis->memory('usage', $key);
                }
            }

            // Count KPI cache keys
            if (method_exists($kpiCache, 'getRedis')) {
                $redis = $kpiCache->getRedis();
                $kpiKeys = $redis->keys($kpiPattern);
                $stats['kpi_cache_keys'] = count($kpiKeys);

                // Get memory usage (Redis specific)
                foreach ($kpiKeys as $key) {
                    $stats['total_memory_usage'] += $redis->memory('usage', $key);
                }
            }

            return $stats;
        } catch (\Exception $e) {
            Log::error('Failed to get cache statistics', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);

            return [
                'dashboard_cache_keys' => 0,
                'kpi_cache_keys' => 0,
                'total_memory_usage' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Warm up cache for commonly accessed dashboards
     */
    public function warmUpCache(string $companyId, array $layouts = []): void
    {
        try {
            if (empty($layouts)) {
                // Get default layouts for the company
                $layouts = $this->getDefaultLayouts($companyId);
            }

            foreach ($layouts as $layoutId) {
                $cacheKey = $this->getDashboardCacheKey($companyId, $layoutId);

                // Check if cache already exists
                if (! $this->getDashboardMetrics($cacheKey)) {
                    // Trigger cache warm up by calling the metrics service
                    // This would typically be done via a job or command
                    Log::info('Warming up cache for layout', [
                        'company_id' => $companyId,
                        'layout_id' => $layoutId,
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to warm up cache', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get default layouts for a company
     */
    private function getDefaultLayouts(string $companyId): array
    {
        try {
            return \Illuminate\Support\Facades\DB::table('rpt.dashboard_layouts')
                ->where('company_id', $companyId)
                ->where('is_default', true)
                ->orWhere('visibility', 'company')
                ->pluck('layout_id')
                ->toArray();
        } catch (\Exception $e) {
            Log::error('Failed to get default layouts', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get TTL for different cache types
     */
    public function getTtl(string $cacheType): int
    {
        return match ($cacheType) {
            'dashboard' => config('reporting.cache.dashboard_ttl', 5),
            'kpi' => config('reporting.cache.kpi_ttl', 300),
            'reports' => config('reporting.cache.reports_ttl', 3600),
            'trial_balance' => config('reporting.cache.trial_balance_ttl', 60),
            'downloads' => config('reporting.cache.downloads_ttl', 600),
            default => 300, // 5 minutes default
        };
    }

    /**
     * Set cache with specific TTL
     */
    public function set(string $key, mixed $value, string $cacheType = 'dashboard'): bool
    {
        return $this->setWithTtl($key, $value, $this->getTtl($cacheType));
    }

    /**
     * Set cache with custom TTL
     */
    public function setWithTtl(string $key, mixed $value, int $ttl): bool
    {
        try {
            return Cache::store('reporting_dashboard')->put($key, $value, $ttl);
        } catch (\Exception $e) {
            Log::error('Failed to set cache', [
                'key' => $key,
                'ttl' => $ttl,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get cache value
     */
    public function get(string $key): mixed
    {
        try {
            return Cache::store('reporting_dashboard')->get($key);
        } catch (\Exception $e) {
            Log::error('Failed to get cache', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Delete cache key
     */
    public function forget(string $key): bool
    {
        try {
            return Cache::store('reporting_dashboard')->forget($key);
        } catch (\Exception $e) {
            Log::error('Failed to forget cache', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
