<?php

namespace Modules\Reporting\Actions\Dashboard;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Reporting\Jobs\RefreshDashboardJob;
use Modules\Reporting\Services\DashboardCacheService;
use Modules\Reporting\Services\DashboardMetricsService;

class RefreshDashboardAction
{
    public function __construct(
        private DashboardMetricsService $metricsService,
        private DashboardCacheService $cacheService
    ) {}

    /**
     * Execute the dashboard refresh action
     */
    public function execute(string $companyId, string $layoutId, array $parameters = [], bool $async = true): array
    {
        // Validate permissions
        $this->validatePermissions($companyId);

        // Check if refresh is already in progress
        if ($this->cacheService->hasCacheLock($companyId, 'dashboard')) {
            throw new \RuntimeException('Dashboard refresh already in progress. Please wait for the current refresh to complete.');
        }

        // Acquire lock to prevent concurrent refreshes
        $lockAcquired = $this->cacheService->acquireCacheLock($companyId, 'dashboard', 300); // 5 minute lock

        if (! $lockAcquired) {
            throw new \RuntimeException('Unable to acquire refresh lock. Another refresh may be in progress.');
        }

        try {
            if ($async) {
                // Queue the refresh job
                $job = new RefreshDashboardJob($companyId, $layoutId, $parameters);
                $jobId = $job->getJobId();

                dispatch($job)->onQueue('dashboard');

                Log::info('Dashboard refresh job queued', [
                    'company_id' => $companyId,
                    'layout_id' => $layoutId,
                    'job_id' => $jobId,
                ]);

                return [
                    'job_id' => $jobId,
                    'status' => 'queued',
                    'estimated_completion_seconds' => 30,
                    'message' => 'Dashboard refresh has been queued and will begin shortly.',
                ];
            } else {
                // Perform synchronous refresh
                $this->performRefresh($companyId, $layoutId, $parameters);

                return [
                    'status' => 'completed',
                    'message' => 'Dashboard refresh completed successfully.',
                    'refreshed_at' => now()->toISOString(),
                ];
            }
        } catch (\Exception $e) {
            // Release lock on failure
            $this->cacheService->releaseCacheLock($companyId, 'dashboard');

            Log::error('Dashboard refresh failed', [
                'company_id' => $companyId,
                'layout_id' => $layoutId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Force refresh all dashboards for a company
     */
    public function refreshAllDashboards(string $companyId, bool $invalidateCache = true): array
    {
        $this->validatePermissions($companyId);

        if ($invalidateCache) {
            $this->cacheService->invalidateAllCache($companyId);
        }

        // Get all layouts for the company
        $layouts = DB::table('rpt.dashboard_layouts')
            ->where('company_id', $companyId)
            ->pluck('layout_id')
            ->toArray();

        if (empty($layouts)) {
            return [
                'status' => 'no_layouts',
                'message' => 'No dashboard layouts found for this company.',
            ];
        }

        $refreshResults = [];
        foreach ($layouts as $layoutId) {
            try {
                $result = $this->execute($companyId, $layoutId, [], true);
                $refreshResults[] = [
                    'layout_id' => $layoutId,
                    'status' => $result['status'],
                    'job_id' => $result['job_id'] ?? null,
                ];
            } catch (\Exception $e) {
                $refreshResults[] = [
                    'layout_id' => $layoutId,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'status' => 'processing',
            'message' => sprintf('Refresh initiated for %d dashboard layouts.', count($layouts)),
            'results' => $refreshResults,
        ];
    }

    /**
     * Invalidate cache for specific dashboard
     */
    public function invalidateCache(string $companyId, string $layoutId): array
    {
        $this->validatePermissions($companyId);

        $this->cacheService->invalidateDashboardCache($companyId);

        Log::info('Dashboard cache invalidated', [
            'company_id' => $companyId,
            'layout_id' => $layoutId,
        ]);

        return [
            'status' => 'success',
            'message' => 'Dashboard cache invalidated successfully.',
            'cache_cleared_at' => now()->toISOString(),
        ];
    }

    /**
     * Get refresh status for a dashboard
     */
    public function getRefreshStatus(string $companyId, string $layoutId): array
    {
        $this->validatePermissions($companyId);

        $isLocked = $this->cacheService->hasCacheLock($companyId, 'dashboard');
        $cacheKey = $this->cacheService->getDashboardCacheKey($companyId, $layoutId);
        $cachedData = $this->cacheService->getDashboardMetrics($cacheKey);

        return [
            'company_id' => $companyId,
            'layout_id' => $layoutId,
            'refresh_in_progress' => $isLocked,
            'cache_exists' => ! is_null($cachedData),
            'last_refreshed_at' => $cachedData['refreshed_at'] ?? null,
            'cache_ttl' => $this->cacheService->getTtl('dashboard'),
            'status' => $isLocked ? 'refreshing' : (is_null($cachedData) ? 'not_cached' : 'cached'),
        ];
    }

    /**
     * Perform the actual refresh operation
     */
    public function performRefresh(string $companyId, string $layoutId, array $parameters = []): void
    {
        try {
            Log::info('Starting dashboard refresh', [
                'company_id' => $companyId,
                'layout_id' => $layoutId,
                'parameters' => $parameters,
            ]);

            // Invalidate existing cache
            $this->cacheService->invalidateDashboardCache($companyId);

            // Refresh materialized views first
            $this->refreshMaterializedViews($companyId);

            // Get fresh metrics
            $metrics = $this->metricsService->getDashboardMetrics($companyId, $layoutId, $parameters);

            // Store in cache
            $cacheKey = $this->cacheService->getDashboardCacheKey($companyId, $layoutId, $parameters);
            $this->cacheService->storeDashboardMetrics($cacheKey, $metrics);

            Log::info('Dashboard refresh completed', [
                'company_id' => $companyId,
                'layout_id' => $layoutId,
                'cards_count' => count($metrics['cards'] ?? []),
                'refreshed_at' => $metrics['refreshed_at'],
            ]);

        } catch (\Exception $e) {
            Log::error('Dashboard refresh failed', [
                'company_id' => $companyId,
                'layout_id' => $layoutId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Refresh materialized views for the company
     */
    private function refreshMaterializedViews(string $companyId): void
    {
        try {
            // Use the database function created in the migration
            DB::select('SELECT rpt.refresh_reporting_materialized_views(?)', [$companyId]);

            Log::info('Materialized views refreshed', ['company_id' => $companyId]);
        } catch (\Exception $e) {
            Log::error('Failed to refresh materialized views', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);

            // Continue with refresh even if materialized views fail
            // The metrics service will fall back to direct queries
        }
    }

    /**
     * Validate user permissions
     */
    private function validatePermissions(string $companyId): void
    {
        $user = auth()->user();

        if (! $user) {
            throw new \UnauthorizedException('User not authenticated');
        }

        // Check if user has access to the company
        $hasAccess = DB::table('auth.company_user')
            ->where('company_id', $companyId)
            ->where('user_id', $user->id)
            ->exists();

        if (! $hasAccess) {
            throw new \UnauthorizedException('User does not have access to this company');
        }

        // Check for specific reporting permission
        if (! $user->can('reporting.dashboard.view')) {
            throw new \UnauthorizedException('User does not have permission to view reporting dashboards');
        }
    }

    /**
     * Get dashboard refresh statistics
     */
    public function getRefreshStats(string $companyId): array
    {
        $this->validatePermissions($companyId);

        $cacheStats = $this->cacheService->getCacheStats($companyId);
        $layouts = DB::table('rpt.dashboard_layouts')
            ->where('company_id', $companyId)
            ->count();

        $isRefreshing = $this->cacheService->hasCacheLock($companyId, 'dashboard');

        return [
            'company_id' => $companyId,
            'total_layouts' => $layouts,
            'is_refreshing' => $isRefreshing,
            'cache_stats' => $cacheStats,
            'last_check_at' => now()->toISOString(),
        ];
    }
}
