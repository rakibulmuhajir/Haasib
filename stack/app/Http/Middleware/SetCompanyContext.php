<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\User;
use App\Services\ContextService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SetCompanyContext
{
    public function __construct(
        private ContextService $contextService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Get authenticated user
        $user = Auth::user();
        
        if (! $user) {
            return $next($request);
        }

        // Determine active company from request, session, or user's default
        $company = $this->resolveActiveCompany($request, $user);
        
        if (! $company) {
            // Clear any existing company context
            $this->clearRlsContext();
            return $next($request);
        }

        // Set RLS context for database queries
        $this->setRlsContext($company, $user);
        
        // Store company in request context for controllers
        $request->attributes->set('company', $company);
        
        // Share company context with Inertia if it's a web request
        if ($request->inertia()) {
            $this->shareCompanyContext($company, $user);
        }
        
        return $next($request);
    }

    private function resolveActiveCompany(Request $request, User $user): ?Company
    {
        // 1. Check explicit company_id in request header (API routes)
        if ($request->hasHeader('X-Company-Id')) {
            $company = $user->companies()->find($request->header('X-Company-Id'));
            if ($company) {
                // Store in session for future requests
                session(['active_company_id' => $company->id]);
                Log::info('Company context set from header', [
                    'user_id' => $user->id,
                    'company_id' => $company->id,
                    'company_name' => $company->name
                ]);
                return $company;
            }
        }

        // 2. Check explicit company_id in request parameters (API routes)
        if ($request->has('company_id')) {
            $company = $user->companies()->find($request->get('company_id'));
            if ($company) {
                // Store in session for future requests
                session(['active_company_id' => $company->id]);
                Log::info('Company context set from parameter', [
                    'user_id' => $user->id,
                    'company_id' => $company->id,
                    'company_name' => $company->name
                ]);
                return $company;
            }
        }

        // 3. Check company context switching endpoint
        if ($request->routeIs('company.context.switch') && $request->isMethod('POST')) {
            $companyId = $request->input('company_id');
            if ($companyId) {
                $company = $user->companies()->find($companyId);
                if ($company) {
                    session(['active_company_id' => $company->id]);
                    Log::info('Company context switched', [
                        'user_id' => $user->id,
                        'company_id' => $company->id,
                        'company_name' => $company->name
                    ]);
                    return $company;
                }
            }
        }

        // 4. Check session for previously selected company
        if (session('active_company_id')) {
            $company = $user->companies()->find(session('active_company_id'));
            if ($company) {
                return $company;
            } else {
                // Clear invalid company from session
                session()->forget('active_company_id');
            }
        }

        // 5. Use user's default company (first available)
        $company = $user->companies()->first();
        if ($company) {
            session(['active_company_id' => $company->id]);
        }

        return $company;
    }

    private function setRlsContext(Company $company, User $user): void
    {
        // Set PostgreSQL session variables for RLS policies
        DB::statement("SET app.current_company_id = '{$company->id}'");
        DB::statement("SET app.current_user_id = '{$user->id}'");
        
        // Set user's role in this specific company
        $companyUser = $company->users()->where('user_id', $user->id)->first();
        $roleInCompany = $companyUser?->role ?? 'member';
        
        DB::statement("SET app.user_role = '{$roleInCompany}'");
        
        // Set super admin status if applicable
        $isSuperAdmin = $user->system_role === 'system_owner' || $user->system_role === 'super_admin';
        DB::statement("SET app.is_super_admin = " . ($isSuperAdmin ? 'true' : 'false'));
    }

    private function clearRlsContext(): void
    {
        // Clear PostgreSQL session variables
        DB::statement("RESET app.current_company_id");
        DB::statement("RESET app.current_user_id");
        DB::statement("RESET app.user_role");
        DB::statement("RESET app.is_super_admin");
    }

    private function shareCompanyContext(Company $company, User $user): void
    {
        // Share company context with Inertia for frontend components
        $companyUser = $company->users()->where('user_id', $user->id)->first();
        
        request()->inertia()->share([
            'currentCompany' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'industry' => $company->industry,
                'currency' => $company->currency ?? 'USD',
                'userRole' => $companyUser?->role ?? 'member',
                'isActive' => $companyUser?->is_active ?? false,
            ],
            'userCompanies' => $user->companies()->get()->map(function ($comp) use ($user) {
                $compUser = $comp->users()->where('user_id', $user->id)->first();
                return [
                    'id' => $comp->id,
                    'name' => $comp->name,
                    'slug' => $comp->slug,
                    'industry' => $comp->industry,
                    'currency' => $comp->currency ?? 'USD',
                    'userRole' => $compUser?->role ?? 'member',
                    'isActive' => $compUser?->is_active ?? false,
                ];
            }),
        ]);
    }
}
