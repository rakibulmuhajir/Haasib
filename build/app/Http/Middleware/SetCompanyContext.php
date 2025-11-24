<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\ServiceContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to set company context for Row Level Security (RLS) policies.
 * This ensures all database operations are properly scoped to the current company.
 */
class SetCompanyContext
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        // Ensure company is always in session for authenticated users
        if ($user) {
            $this->ensureCompanyInContext($user, $request);
        }
        
        // Create service context from request
        $serviceContext = ServiceContext::fromRequest($request);
        
        // Set database context for RLS policies
        $this->setDatabaseContext($serviceContext);
        
        // Store service context in request for use by controllers/services
        $request->attributes->set('service_context', $serviceContext);
        
        // Log context for audit trail
        $this->logContextSwitch($serviceContext);

        return $next($request);
    }

    /**
     * Ensure a company is always set in the session for authenticated users.
     */
    private function ensureCompanyInContext(User $user, Request $request): void
    {
        $companyContextManager = app(\App\Services\CompanyContextManager::class);
        
        // Get current active company from session or auto-resolve one
        $currentCompanyId = $request->session()->get('active_company_id');
        
        if (!$currentCompanyId) {
            // Auto-select first available company if none in session
            $userCompanies = $companyContextManager->getUserCompanies($user);
            
            if (!empty($userCompanies)) {
                $firstCompany = $userCompanies[0];
                $request->session()->put('active_company_id', $firstCompany['id']);
                
                Log::info('Auto-selected company for user', [
                    'user_id' => $user->id,
                    'company_id' => $firstCompany['id'],
                    'company_name' => $firstCompany['name'],
                    'reason' => 'no_active_company_in_session'
                ]);
            }
        }
    }

    /**
     * Set PostgreSQL session variables for RLS policies.
     */
    private function setDatabaseContext(ServiceContext $serviceContext): void
    {
        try {
            // Set company context
            if ($serviceContext->hasCompany()) {
                $companyId = addslashes($serviceContext->getCompanyId());
                DB::statement("SET app.current_company_id = '{$companyId}'");
            } else {
                DB::statement("SET app.current_company_id = ''");
            }

            // Set user context  
            if ($serviceContext->hasUser()) {
                $userId = addslashes($serviceContext->getUserId());
                DB::statement("SET app.current_user_id = '{$userId}'");
            } else {
                DB::statement("SET app.current_user_id = ''");
            }

            // Set request ID for correlation
            $requestId = addslashes($serviceContext->getRequestId());
            DB::statement("SET app.current_request_id = '{$requestId}'");

        } catch (\Exception $e) {
            Log::error('Failed to set company context for RLS', [
                'user_id' => $serviceContext->getUserId(),
                'company_id' => $serviceContext->getCompanyId(),
                'request_id' => $serviceContext->getRequestId(),
                'error' => $e->getMessage(),
            ]);
            
            // Don't fail the request, but ensure empty context is set
            try {
                DB::statement("SET app.current_company_id = ''");
                DB::statement("SET app.current_user_id = ''");
                DB::statement("SET app.current_request_id = ''");
            } catch (\Exception $fallbackError) {
                Log::critical('Failed to set fallback RLS context', [
                    'original_error' => $e->getMessage(),
                    'fallback_error' => $fallbackError->getMessage(),
                ]);
            }
        }
    }

    /**
     * Log context switch for audit trail.
     */
    private function logContextSwitch(ServiceContext $serviceContext): void
    {
        if ($serviceContext->hasUser() || $serviceContext->hasCompany()) {
            Log::info('Company context set for request', [
                'user_id' => $serviceContext->getUserId(),
                'company_id' => $serviceContext->getCompanyId(),
                'request_id' => $serviceContext->getRequestId(),
                'route' => $serviceContext->getMetadata()['route'] ?? null,
                'ip' => $serviceContext->getMetadata()['ip'] ?? null,
            ]);
        }
    }
}