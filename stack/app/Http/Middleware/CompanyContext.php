<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CompanyContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Get company from various sources
        $company = $this->getCompanyFromRequest($request, $user);

        if (! $company) {
            // Try to get default company for the user
            $company = $user->companies()->first();

            if (! $company) {
                return response()->json([
                    'error' => 'No company context found',
                    'message' => 'You must be associated with a company to access this resource',
                ], 403);
            }
        }

        // Verify user has access to this company
        if (! $user->companies()->where('companies.id', $company->id)->exists()) {
            return response()->json([
                'error' => 'Access denied',
                'message' => 'You do not have access to this company',
            ], 403);
        }

        // Add company to request attributes for later use
        $request->attributes->set('company', $company);

        // Set company context for models
        if (method_exists($user, 'setCurrentCompany')) {
            $user->setCurrentCompany($company);
        }

        return $next($request);
    }

    private function getCompanyFromRequest(Request $request, $user): ?Company
    {
        // Try from X-Company-ID header (preferred for API)
        $companyId = $request->header('X-Company-ID');
        if ($companyId) {
            return Company::find($companyId);
        }

        // Try from session (web requests)
        $sessionCompanyId = session('current_company_id');
        if ($sessionCompanyId) {
            return Company::find($sessionCompanyId);
        }

        // Try from request parameter
        $requestCompanyId = $request->get('company_id');
        if ($requestCompanyId) {
            return Company::find($requestCompanyId);
        }

        // Try from route parameter
        $routeCompanyId = $request->route('company_id');
        if ($routeCompanyId) {
            return Company::find($routeCompanyId);
        }

        return null;
    }
}
