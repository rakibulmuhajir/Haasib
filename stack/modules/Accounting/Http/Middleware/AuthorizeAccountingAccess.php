<?php

namespace Modules\Accounting\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeAccountingAccess
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Ensure user is authenticated
        if (! Auth::check()) {
            return response()->json([
                'message' => 'Authentication required',
                'error' => 'unauthenticated',
            ], 401);
        }

        // Ensure user has an active company
        if (! $request->user()->current_company_id) {
            return response()->json([
                'message' => 'No active company selected',
                'error' => 'no_company_context',
            ], 403);
        }

        // Validate company access for multi-tenant security
        $companyId = $request->route('company_id') ?? $request->user()->current_company_id;

        if (! $request->user()->companies()->where('companies.id', $companyId)->exists()) {
            return response()->json([
                'message' => 'Access denied to this company',
                'error' => 'company_access_denied',
            ], 403);
        }

        // Add company context to request for easy access in controllers
        $request->merge(['validated_company_id' => $companyId]);

        return $next($request);
    }
}
