<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Company;
use App\Services\CompanyContextManager;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CompanyContextHealthController extends Controller
{
    public function __construct(
        private CompanyContextManager $companyContextManager
    ) {}

    /**
     * Get comprehensive health check of the company context system.
     */
    public function healthCheck(Request $request): JsonResponse
    {
        $healthChecks = [];
        $overallHealth = 'healthy';
        $startTime = microtime(true);

        try {
            // 1. Database connectivity
            $healthChecks['database'] = $this->checkDatabase();
            if ($healthChecks['database']['status'] !== 'healthy') {
                $overallHealth = 'unhealthy';
            }

            // 2. Cache connectivity
            $healthChecks['cache'] = $this->checkCache();
            if ($healthChecks['cache']['status'] !== 'healthy') {
                $overallHealth = 'degraded';
            }

            // 3. Company resolution performance
            $healthChecks['resolution_performance'] = $this->checkResolutionPerformance();
            if ($healthChecks['resolution_performance']['status'] !== 'healthy') {
                $overallHealth = 'degraded';
            }

            // 4. Data integrity
            $healthChecks['data_integrity'] = $this->checkDataIntegrity();
            if ($healthChecks['data_integrity']['status'] !== 'healthy') {
                $overallHealth = 'degraded';
            }

            // 5. User company associations
            $healthChecks['user_associations'] = $this->checkUserAssociations();
            if ($healthChecks['user_associations']['status'] !== 'healthy') {
                $overallHealth = 'degraded';
            }

        } catch (\Exception $e) {
            Log::error('Company context health check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $overallHealth = 'unhealthy';
            $healthChecks['error'] = [
                'status' => 'unhealthy',
                'message' => 'Health check failed: ' . $e->getMessage(),
            ];
        }

        $totalTime = round((microtime(true) - $startTime) * 1000, 2);

        return response()->json([
            'status' => $overallHealth,
            'timestamp' => now()->toISOString(),
            'total_time_ms' => $totalTime,
            'checks' => $healthChecks,
            'summary' => $this->generateHealthSummary($healthChecks),
        ]);
    }

    /**
     * Get detailed metrics about the company context system.
     */
    public function metrics(Request $request): JsonResponse
    {
        try {
            $metrics = [
                'companies' => $this->getCompanyMetrics(),
                'users' => $this->getUserMetrics(),
                'cache' => $this->getCacheMetrics(),
                'performance' => $this->getPerformanceMetrics(),
                'errors' => $this->getErrorMetrics(),
            ];

            return response()->json([
                'status' => 'success',
                'timestamp' => now()->toISOString(),
                'metrics' => $metrics,
            ]);

        } catch (\Exception $e) {
            Log::error('Company context metrics failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to collect metrics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test company resolution for a specific user.
     */
    public function testResolution(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:auth.users,id',
        ]);

        try {
            $user = User::findOrFail($request->user_id);
            $startTime = microtime(true);

            $activeCompany = $this->companyContextManager->getActiveCompany($user, $request);
            $userCompanies = $this->companyContextManager->getUserCompanies($user);
            $debugInfo = $this->companyContextManager->getDebugInfo($user, $request);

            $resolutionTime = round((microtime(true) - $startTime) * 1000, 2);

            return response()->json([
                'status' => 'success',
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'name' => $user->name,
                ],
                'resolution' => [
                    'active_company' => $activeCompany,
                    'user_companies' => $userCompanies,
                    'resolution_time_ms' => $resolutionTime,
                ],
                'debug' => $debugInfo,
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Company context test resolution failed', [
                'user_id' => $request->user_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Resolution test failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check database connectivity.
     */
    private function checkDatabase(): array
    {
        try {
            $startTime = microtime(true);
            DB::connection()->getPdo();
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'status' => 'healthy',
                'message' => 'Database connection successful',
                'response_time_ms' => $responseTime,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Database connection failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check cache connectivity.
     */
    private function checkCache(): array
    {
        try {
            $testKey = 'company_context_health_check';
            $testValue = time();
            
            $startTime = microtime(true);
            Cache::put($testKey, $testValue, 60);
            $retrieved = Cache::get($testKey);
            Cache::forget($testKey);
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            if ($retrieved === $testValue) {
                return [
                    'status' => 'healthy',
                    'message' => 'Cache is working correctly',
                    'response_time_ms' => $responseTime,
                ];
            } else {
                return [
                    'status' => 'unhealthy',
                    'message' => 'Cache read/write mismatch',
                ];
            }
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Cache test failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check company resolution performance.
     */
    private function checkResolutionPerformance(): array
    {
        try {
            // Test with a sample user
            $user = User::with('companies')->first();
            
            if (!$user) {
                return [
                    'status' => 'degraded',
                    'message' => 'No users available for performance test',
                ];
            }

            $startTime = microtime(true);
            $this->companyContextManager->getActiveCompany($user);
            $resolutionTime = round((microtime(true) - $startTime) * 1000, 2);

            $status = 'healthy';
            $message = 'Company resolution performance is good';

            if ($resolutionTime > 100) {
                $status = 'degraded';
                $message = 'Company resolution is slow';
            } elseif ($resolutionTime > 50) {
                $status = 'degraded';
                $message = 'Company resolution could be faster';
            }

            return [
                'status' => $status,
                'message' => $message,
                'resolution_time_ms' => $resolutionTime,
                'threshold_ms' => 50,
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Performance test failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check data integrity.
     */
    private function checkDataIntegrity(): array
    {
        try {
            $issues = [];

            // Check for users with invalid preferred_company_id
            $invalidPreferences = User::whereNotNull('preferred_company_id')
                ->whereNotExists(function ($query) {
                    $query->select('id')
                          ->from('auth.companies')
                          ->whereColumn('auth.companies.id', 'auth.users.preferred_company_id');
                })
                ->count();

            if ($invalidPreferences > 0) {
                $issues[] = "Found {$invalidPreferences} users with invalid preferred company IDs";
            }

            // Check for orphaned company users
            $orphanedAssociations = DB::table('auth.company_user')
                ->whereNotExists(function ($query) {
                    $query->select('id')
                          ->from('auth.users')
                          ->whereColumn('auth.users.id', 'auth.company_user.user_id');
                })
                ->orWhereNotExists(function ($query) {
                    $query->select('id')
                          ->from('auth.companies')
                          ->whereColumn('auth.companies.id', 'auth.company_user.company_id');
                })
                ->count();

            if ($orphanedAssociations > 0) {
                $issues[] = "Found {$orphanedAssociations} orphaned company-user associations";
            }

            if (empty($issues)) {
                return [
                    'status' => 'healthy',
                    'message' => 'Data integrity checks passed',
                ];
            } else {
                return [
                    'status' => 'degraded',
                    'message' => 'Data integrity issues found',
                    'issues' => $issues,
                ];
            }

        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Data integrity check failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check user-company associations.
     */
    private function checkUserAssociations(): array
    {
        try {
            $userCount = User::count();
            $usersWithCompanies = User::whereHas('companies')->count();
            $usersWithoutCompanies = $userCount - $usersWithCompanies;
            $averageCompaniesPerUser = $userCount > 0 
                ? round(DB::table('auth.company_user')->count() / $userCount, 2)
                : 0;

            $status = 'healthy';
            $message = 'User associations look good';

            if ($usersWithoutCompanies > ($userCount * 0.5)) {
                $status = 'degraded';
                $message = 'Many users have no company associations';
            }

            return [
                'status' => $status,
                'message' => $message,
                'stats' => [
                    'total_users' => $userCount,
                    'users_with_companies' => $usersWithCompanies,
                    'users_without_companies' => $usersWithoutCompanies,
                    'average_companies_per_user' => $averageCompaniesPerUser,
                ],
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'User associations check failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get company-related metrics.
     */
    private function getCompanyMetrics(): array
    {
        return [
            'total_companies' => Company::count(),
            'active_companies' => Company::where('is_active', true)->count(),
            'companies_with_users' => Company::has('users')->count(),
            'companies_without_users' => Company::doesntHave('users')->count(),
        ];
    }

    /**
     * Get user-related metrics.
     */
    private function getUserMetrics(): array
    {
        return [
            'total_users' => User::count(),
            'users_with_companies' => User::has('companies')->count(),
            'users_with_preferred_company' => User::whereNotNull('preferred_company_id')->count(),
            'system_users' => User::whereIn('system_role', ['superadmin', 'systemadmin'])->count(),
        ];
    }

    /**
     * Get cache-related metrics.
     */
    private function getCacheMetrics(): array
    {
        // Note: Actual cache metrics depend on your cache driver
        return [
            'cache_driver' => config('cache.default'),
            'note' => 'Detailed cache metrics depend on cache driver implementation',
        ];
    }

    /**
     * Get performance metrics.
     */
    private function getPerformanceMetrics(): array
    {
        // This would be enhanced with actual performance tracking
        return [
            'note' => 'Performance metrics would be collected from logs or APM tools',
            'resolution_threshold_ms' => 50,
        ];
    }

    /**
     * Get error metrics.
     */
    private function getErrorMetrics(): array
    {
        // This would be enhanced with actual error tracking
        return [
            'note' => 'Error metrics would be collected from logs or error tracking services',
        ];
    }

    /**
     * Generate a health summary.
     */
    private function generateHealthSummary(array $healthChecks): array
    {
        $total = count($healthChecks);
        $healthy = 0;
        $degraded = 0;
        $unhealthy = 0;

        foreach ($healthChecks as $check) {
            switch ($check['status'] ?? 'unknown') {
                case 'healthy':
                    $healthy++;
                    break;
                case 'degraded':
                    $degraded++;
                    break;
                case 'unhealthy':
                    $unhealthy++;
                    break;
            }
        }

        return [
            'total_checks' => $total,
            'healthy' => $healthy,
            'degraded' => $degraded,
            'unhealthy' => $unhealthy,
            'health_percentage' => $total > 0 ? round(($healthy / $total) * 100, 1) : 0,
        ];
    }
}