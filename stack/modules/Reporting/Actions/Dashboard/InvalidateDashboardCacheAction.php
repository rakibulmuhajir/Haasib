<?php

namespace Modules\Reporting\Actions\Dashboard;

use Illuminate\Support\Facades\Log;
use Modules\Reporting\Services\DashboardCacheService;

class InvalidateDashboardCacheAction
{
    public function __construct(
        private DashboardCacheService $cacheService
    ) {}

    /**
     * Execute cache invalidation
     */
    public function execute(string $companyId, ?string $layoutId = null): array
    {
        try {
            if ($layoutId) {
                // Invalidate specific dashboard layout
                $this->invalidateSpecificLayout($companyId, $layoutId);
            } else {
                // Invalidate all dashboard cache for company
                $this->cacheService->invalidateDashboardCache($companyId);
            }

            Log::info('Dashboard cache invalidated', [
                'company_id' => $companyId,
                'layout_id' => $layoutId,
            ]);

            return [
                'status' => 'success',
                'message' => $layoutId
                    ? "Cache invalidated for layout: {$layoutId}"
                    : "All dashboard cache invalidated for company: {$companyId}",
                'invalidated_at' => now()->toISOString(),
            ];

        } catch (\Exception $e) {
            Log::error('Failed to invalidate dashboard cache', [
                'company_id' => $companyId,
                'layout_id' => $layoutId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Invalidate cache for specific layout
     */
    private function invalidateSpecificLayout(string $companyId, string $layoutId): void
    {
        $patterns = [
            $this->cacheService->getDashboardCacheKey($companyId, $layoutId),
            // Also invalidate cache with different parameter combinations
            $this->cacheService->getDashboardCacheKey($companyId, $layoutId, ['comparison' => 'prior_period']),
            $this->cacheService->getDashboardCacheKey($companyId, $layoutId, ['comparison' => 'prior_year']),
        ];

        foreach ($patterns as $key) {
            $this->cacheService->forget($key);
        }
    }
}
