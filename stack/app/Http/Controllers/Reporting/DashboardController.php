<?php

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Modules\Reporting\Services\DashboardMetricsService;
use Modules\Reporting\Actions\Dashboard\RefreshDashboardAction;
use Modules\Reporting\Actions\Dashboard\InvalidateDashboardCacheAction;
use Modules\Reporting\Services\DashboardCacheService;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardMetricsService $metricsService,
        private RefreshDashboardAction $refreshAction,
        private InvalidateDashboardCacheAction $invalidateCacheAction,
        private DashboardCacheService $cacheService
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:reporting.dashboard.view')->only(['index', 'show', 'status']);
        $this->middleware('permission:reporting.dashboard.refresh')->only(['refresh', 'invalidateCache']);
    }

    /**
     * Get dashboard data for a specific layout
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'layout_id' => ['required', 'string', 'uuid'],
            'date_range.start' => ['nullable', 'date', 'before_or_equal:date_range.end'],
            'date_range.end' => ['nullable', 'date', 'after_or_equal:date_range.start'],
            'comparison' => ['nullable', 'string', Rule::in(['prior_period', 'prior_year', 'custom'])],
            'filters.segment' => ['nullable', 'string'],
            'currency' => ['nullable', 'string', 'size:3'],
            'force_refresh' => ['nullable', 'boolean'],
        ]);

        $companyId = $request->user()->current_company_id;
        $layoutId = $validated['layout_id'];
        $parameters = $this->extractParameters($validated);

        try {
            // Force refresh if requested
            if ($validated['force_refresh'] ?? false) {
                $this->invalidateCacheAction->execute($companyId, $layoutId);
            }

            // Get dashboard metrics
            $dashboardData = $this->metricsService->getDashboardMetrics($companyId, $layoutId, $parameters);

            // Get cache TTL for response header
            $cacheTtl = $this->cacheService->getTtl('dashboard');

            return response()->json($dashboardData)
                ->header('X-Cache-TTL', $cacheTtl)
                ->header('X-Content-Type-Options', 'nosniff');

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'validation_error',
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'You do not have permission to access this dashboard.',
            ], Response::HTTP_FORBIDDEN);

        } catch (\Exception $e) {
            Log::error('Dashboard fetch failed', [
                'company_id' => $companyId,
                'layout_id' => $layoutId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'internal_error',
                'message' => 'Failed to fetch dashboard data. Please try again later.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Refresh dashboard cache
     */
    public function refresh(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'layout_id' => ['required', 'string', 'uuid'],
            'invalidate_cache' => ['boolean', 'default: true'],
            'priority' => ['nullable', 'string', Rule::in(['low', 'normal', 'high'])],
            'async' => ['boolean', 'default: true'],
            'date_range.start' => ['nullable', 'date'],
            'date_range.end' => ['nullable', 'date'],
            'comparison' => ['nullable', 'string', Rule::in(['prior_period', 'prior_year', 'custom'])],
            'filters.segment' => ['nullable', 'string'],
            'currency' => ['nullable', 'string', 'size:3'],
        ]);

        $companyId = $request->user()->current_company_id;
        $layoutId = $validated['layout_id'];
        $parameters = $this->extractParameters($validated);

        // Set defaults for optional parameters
        $invalidateCache = $validated['invalidate_cache'] ?? true;
        $async = $validated['async'] ?? true;

        try {
            $result = $this->refreshAction->execute(
                $companyId,
                $layoutId,
                $parameters,
                $async
            );

            return response()->json($result, Response::HTTP_ACCEPTED);

        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), 'already in progress')) {
                return response()->json([
                    'error' => 'conflict',
                    'message' => $e->getMessage(),
                ], Response::HTTP_CONFLICT);
            }

            return response()->json([
                'error' => 'runtime_error',
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'You do not have permission to refresh this dashboard.',
            ], Response::HTTP_FORBIDDEN);

        } catch (\Exception $e) {
            Log::error('Dashboard refresh failed', [
                'company_id' => $companyId,
                'layout_id' => $layoutId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'internal_error',
                'message' => 'Failed to refresh dashboard. Please try again later.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Refresh all dashboards for a company
     */
    public function refreshAll(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invalidate_cache' => ['boolean', 'default: true'],
        ]);

        $companyId = $request->user()->current_company_id;
        $invalidateCache = $validated['invalidate_cache'] ?? true;

        try {
            $result = $this->refreshAction->refreshAllDashboards(
                $companyId,
                $invalidateCache
            );

            return response()->json($result);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'You do not have permission to refresh dashboards.',
            ], Response::HTTP_FORBIDDEN);

        } catch (\Exception $e) {
            Log::error('Refresh all dashboards failed', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'internal_error',
                'message' => 'Failed to refresh dashboards. Please try again later.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Invalidate dashboard cache
     */
    public function invalidateCache(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'layout_id' => ['nullable', 'string', 'uuid'],
        ]);

        $companyId = $request->user()->current_company_id;
        $layoutId = $validated['layout_id'] ?? null;

        try {
            $result = $this->invalidateCacheAction->execute($companyId, $layoutId);

            return response()->json($result);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'You do not have permission to invalidate dashboard cache.',
            ], Response::HTTP_FORBIDDEN);

        } catch (\Exception $e) {
            Log::error('Cache invalidation failed', [
                'company_id' => $companyId,
                'layout_id' => $layoutId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'internal_error',
                'message' => 'Failed to invalidate cache. Please try again later.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get dashboard refresh status
     */
    public function status(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'layout_id' => ['required', 'string', 'uuid'],
        ]);

        $companyId = $request->user()->current_company_id;
        $layoutId = $validated['layout_id'];

        try {
            $status = $this->refreshAction->getRefreshStatus($companyId, $layoutId);

            return response()->json($status);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'You do not have permission to view dashboard status.',
            ], Response::HTTP_FORBIDDEN);

        } catch (\Exception $e) {
            Log::error('Dashboard status check failed', [
                'company_id' => $companyId,
                'layout_id' => $layoutId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'internal_error',
                'message' => 'Failed to get dashboard status. Please try again later.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get dashboard statistics
     */
    public function stats(Request $request): JsonResponse
    {
        $companyId = $request->user()->current_company_id;

        try {
            $stats = $this->refreshAction->getRefreshStats($companyId);

            return response()->json($stats);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'You do not have permission to view dashboard statistics.',
            ], Response::HTTP_FORBIDDEN);

        } catch (\Exception $e) {
            Log::error('Dashboard stats failed', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'internal_error',
                'message' => 'Failed to get dashboard statistics. Please try again later.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get available dashboard layouts for the current company
     */
    public function layouts(Request $request): JsonResponse
    {
        $companyId = $request->user()->current_company_id;

        try {
            $layouts = \Illuminate\Support\Facades\DB::table('rpt.dashboard_layouts')
                ->where('company_id', $companyId)
                ->where(function ($query) use ($request) {
                    $query->where('visibility', 'company')
                          ->orWhere('owner_id', $request->user()->id)
                          ->orWhereJsonContains('applies_to_roles', $request->user()->getRoleNames());
                })
                ->select([
                    'layout_id',
                    'name',
                    'visibility',
                    'is_default',
                    'created_at',
                    'updated_at'
                ])
                ->orderBy('is_default', 'desc')
                ->orderBy('name')
                ->get()
                ->map(function ($layout) {
                    return [
                        'layout_id' => $layout->layout_id,
                        'name' => $layout->name,
                        'visibility' => $layout->visibility,
                        'is_default' => $layout->is_default,
                        'created_at' => $layout->created_at,
                        'updated_at' => $layout->updated_at,
                    ];
                });

            return response()->json([
                'data' => $layouts,
                'total' => $layouts->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Dashboard layouts fetch failed', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'internal_error',
                'message' => 'Failed to fetch dashboard layouts. Please try again later.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Extract parameters from request
     */
    private function extractParameters(array $validated): array
    {
        $parameters = [];

        if (isset($validated['date_range'])) {
            $parameters['date_range'] = $validated['date_range'];
        }

        if (isset($validated['comparison'])) {
            $parameters['comparison'] = $validated['comparison'];
        }

        if (isset($validated['filters'])) {
            $parameters['filters'] = $validated['filters'];
        }

        if (isset($validated['currency'])) {
            $parameters['currency'] = $validated['currency'];
        }

        return $parameters;
    }

    /**
     * Get aging KPIs for dashboard display
     */
    public function agingKpis(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date'],
            'aging_buckets' => ['nullable', 'array'],
            'aging_buckets.*' => ['integer', 'min:1'],
            'currency' => ['nullable', 'string', 'size:3'],
            'include_summary' => ['nullable', 'boolean'],
        ]);

        $companyId = $request->user()->current_company_id;

        try {
            $kpiService = new \Modules\Reporting\Services\KpiComputationService(
                new \Modules\Reporting\Services\CurrencyConversionService()
            );

            $agingData = $kpiService->computeAgingKpis($companyId, [
                'date' => $validated['date'] ?? now()->toDateString(),
                'aging_buckets' => $validated['aging_buckets'] ?? [30, 60, 90, 120],
                'currency' => $validated['currency'] ?? 'USD',
            ]);

            // Format for dashboard display
            $dashboardData = [
                'receivables_aging' => $this->formatAgingForDashboard($agingData['receivables_aging']),
                'payables_aging' => $this->formatAgingForDashboard($agingData['payables_aging']),
                'aging_metrics' => $agingData['aging_metrics'],
                'as_of_date' => $agingData['as_of_date'],
                'currency' => $agingData['currency'],
            ];

            // Include summary metrics if requested
            if ($validated['include_summary'] ?? true) {
                $dashboardData['summary'] = [
                    'total_receivables' => $agingData['receivables_aging']['total'],
                    'total_payables' => $agingData['payables_aging']['total'],
                    'aging_health_score' => $agingData['aging_metrics']['aging_health_score'],
                    'collection_effectiveness' => $agingData['aging_metrics']['collection_effectiveness'],
                ];
            }

            return response()->json($dashboardData);

        } catch (\Exception $e) {
            Log::error('Dashboard aging KPIs failed', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'internal_error',
                'message' => 'Failed to fetch aging KPIs.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Format aging data for dashboard display
     */
    protected function formatAgingForDashboard(array $aging): array
    {
        $formatted = [
            'total' => $aging['total'],
            'buckets' => [],
        ];

        foreach ($aging['buckets'] as $key => $bucket) {
            $formatted['buckets'][] = [
                'label' => $this->formatAgingBucketLabel($bucket['days']),
                'amount' => $bucket['amount'],
                'count' => $bucket['count'],
                'percentage' => $bucket['percentage'],
                'color' => $this->getAgingBucketColor($bucket['days']),
            ];
        }

        return $formatted;
    }

    /**
     * Format aging bucket label
     */
    protected function formatAgingBucketLabel(string $days): string
    {
        if (str_starts_with($days, '>')) {
            return 'Over ' . substr($days, 2);
        }
        return "{$days} Days";
    }

    /**
     * Get aging bucket color for visualization
     */
    protected function getAgingBucketColor(string $days): string
    {
        if ($days <= 30) return 'green';
        if ($days <= 60) return 'yellow';
        if ($days <= 90) return 'orange';
        if ($days <= 120) return 'red';
        return 'dark-red';
    }
}
