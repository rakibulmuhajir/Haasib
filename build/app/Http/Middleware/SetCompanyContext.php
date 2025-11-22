<?php

namespace App\Http\Middleware;

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