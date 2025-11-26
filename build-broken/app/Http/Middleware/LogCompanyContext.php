<?php

namespace App\Http\Middleware;

use App\Services\CompanyContextManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class LogCompanyContext
{
    public function __construct(
        private CompanyContextManager $companyContextManager
    ) {}

    /**
     * Handle an incoming request and log company context information.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $response = $next($request);

        // Only log for authenticated users
        if (!Auth::check()) {
            return $response;
        }

        // Skip logging for certain routes to avoid noise
        if ($this->shouldSkipLogging($request)) {
            return $response;
        }

        try {
            $user = Auth::user();
            $this->logCompanyContext($request, $user, $response);
        } catch (\Exception $e) {
            // Don't fail the request if logging fails
            Log::error('CompanyContextLogging failed', [
                'error' => $e->getMessage(),
                'url' => $request->url(),
                'user_id' => Auth::id(),
            ]);
        }

        return $response;
    }

    /**
     * Log company context information.
     */
    private function logCompanyContext(Request $request, $user, $response): void
    {
        $startTime = microtime(true);
        
        $activeCompany = $this->companyContextManager->getActiveCompany($user, $request);
        $resolutionTime = round((microtime(true) - $startTime) * 1000, 2);

        $logData = [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'url' => $request->url(),
            'method' => $request->method(),
            'route_name' => $request->route()?->getName(),
            'session_id' => $request->session()->getId(),
            'company_context' => [
                'active_company_id' => $activeCompany['id'] ?? null,
                'active_company_name' => $activeCompany['name'] ?? null,
                'user_role' => $activeCompany['user_role'] ?? null,
                'resolution_time_ms' => $resolutionTime,
            ],
            'session_data' => [
                'active_company_id' => $request->session()->get('active_company_id'),
            ],
            'user_data' => [
                'preferred_company_id' => $user->preferred_company_id,
                'system_role' => $user->system_role,
            ],
            'response_status' => $response->getStatusCode(),
            'timestamp' => now()->toISOString(),
        ];

        // Add performance warning if resolution is slow
        if ($resolutionTime > 100) {
            $logData['performance_warning'] = 'Company context resolution took longer than 100ms';
            Log::warning('Slow company context resolution', $logData);
        } else {
            Log::info('Company context', $logData);
        }

        // Log context switches
        $previousCompanyId = $request->session()->get('previous_company_id');
        $currentCompanyId = $activeCompany['id'] ?? null;
        
        if ($previousCompanyId && $previousCompanyId !== $currentCompanyId) {
            Log::info('Company context changed', [
                'user_id' => $user->id,
                'from_company_id' => $previousCompanyId,
                'to_company_id' => $currentCompanyId,
                'url' => $request->url(),
                'timestamp' => now()->toISOString(),
            ]);
            
            // Update session to track current company for next request
            $request->session()->put('previous_company_id', $currentCompanyId);
        } elseif (!$previousCompanyId && $currentCompanyId) {
            // First time setting company context
            $request->session()->put('previous_company_id', $currentCompanyId);
        }
    }

    /**
     * Determine if we should skip logging for this request.
     */
    private function shouldSkipLogging(Request $request): bool
    {
        // Skip logging for certain routes to avoid noise
        $skipRoutes = [
            'debugbar.*',
            'horizon.*',
            '_debugbar.*',
        ];

        $routeName = $request->route()?->getName();
        if (!$routeName) {
            return false;
        }

        foreach ($skipRoutes as $pattern) {
            if (fnmatch($pattern, $routeName)) {
                return true;
            }
        }

        // Skip logging for asset requests
        if ($request->is('*.js', '*.css', '*.png', '*.jpg', '*.gif', '*.svg', '*.ico')) {
            return true;
        }

        // Skip logging for frequent API calls
        if ($request->is('api/health*', 'api/status*')) {
            return true;
        }

        return false;
    }
}