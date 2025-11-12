<?php

namespace App\Http\Controllers;

use App\Services\DashboardMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardMetricsService $metricsService
    ) {
        $this->middleware('auth');
    }

    /**
     * Show the main dashboard page
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $company = $user->currentCompany;

        if (! $company) {
            return Inertia::render('Dashboard', [
                'metrics' => null,
                'error' => 'No company selected. Please select a company to view dashboard metrics.',
            ]);
        }

        try {
            $metrics = $this->metricsService->getCompanyMetrics($company);

            return Inertia::render('Dashboard', [
                'metrics' => $metrics,
                'company' => [
                    'id' => $company->id,
                    'name' => $company->name,
                    'currency' => $company->currency_code ?? 'USD',
                ],
                'lastUpdated' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Dashboard metrics fetch failed', [
                'company_id' => $company->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return Inertia::render('Dashboard', [
                'metrics' => null,
                'error' => 'Failed to load dashboard metrics. Please try again later.',
                'company' => [
                    'id' => $company->id,
                    'name' => $company->name,
                    'currency' => $company->currency_code ?? 'USD',
                ],
            ]);
        }
    }

    /**
     * Get dashboard metrics as JSON (for AJAX updates)
     */
    public function metrics(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $user->currentCompany;

        if (! $company) {
            return response()->json([
                'error' => 'No company selected',
            ], 400);
        }

        try {
            $metrics = $this->metricsService->getCompanyMetrics($company);

            return response()->json([
                'metrics' => $metrics,
                'company' => [
                    'id' => $company->id,
                    'name' => $company->name,
                    'currency' => $company->currency_code ?? 'USD',
                ],
                'lastUpdated' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Dashboard metrics API call failed', [
                'company_id' => $company->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to fetch dashboard metrics',
            ], 500);
        }
    }

    /**
     * Refresh dashboard metrics
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $user->currentCompany;

        if (! $company) {
            return response()->json([
                'error' => 'No company selected',
            ], 400);
        }

        try {
            // Force refresh by bypassing any cache
            $metrics = $this->metricsService->getCompanyMetrics($company);

            return response()->json([
                'metrics' => $metrics,
                'company' => [
                    'id' => $company->id,
                    'name' => $company->name,
                    'currency' => $company->currency_code ?? 'USD',
                ],
                'lastUpdated' => now()->toISOString(),
                'refreshed' => true,
            ]);

        } catch (\Exception $e) {
            Log::error('Dashboard refresh failed', [
                'company_id' => $company->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to refresh dashboard metrics',
            ], 500);
        }
    }
}
