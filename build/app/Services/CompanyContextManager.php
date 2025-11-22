<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * CompanyContextManager - Centralized company context management service.
 * 
 * This service provides a single source of truth for company context resolution,
 * caching, persistence, and validation across the entire application.
 */
class CompanyContextManager
{
    /**
     * Build a stable cache key for the active company context.
     */
    private function contextCacheKey(User $user): string
    {
        return "company_context:{$user->id}";
    }

    /**
     * Build a stable cache key for a user's companies list.
     */
    private function userCompaniesCacheKey(User $user): string
    {
        return "user_companies:{$user->id}";
    }

    /**
     * Company resolution priority order.
     */
    const RESOLUTION_PRIORITY = [
        'route',      // Company from route parameter
        'session',    // Active company from session
        'database',   // Preferred company from user table
        'first',      // First available company for user
    ];

    /**
     * Cache TTL for company context (5 minutes).
     */
    const CACHE_TTL = 300;

    /**
     * Get the current active company for a user with full context.
     */
    public function getActiveCompany(?User $user = null, ?Request $request = null): ?array
    {
        if (!$user) {
            return null;
        }

        $cacheKey = $this->contextCacheKey($user);
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user, $request) {
            return $this->resolveActiveCompany($user, $request);
        });
    }

    /**
     * Get all companies for a user with enhanced data.
     */
    public function getUserCompanies(?User $user = null): array
    {
        if (!$user) {
            return [];
        }

        $cacheKey = $this->userCompaniesCacheKey($user);
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user) {
            return $this->fetchUserCompanies($user);
        });
    }

    /**
     * Switch user to a different company context.
     */
    public function switchToCompany(User $user, ?string $companyId, ?Request $request = null): array
    {
        $result = [
            'success' => false,
            'message' => '',
            'company' => null,
            'previous_company' => $this->getActiveCompanyId($user),
        ];

        try {
            // Validate company access
            if ($companyId && !$this->canUserAccessCompany($user, $companyId)) {
                $result['message'] = 'You do not have access to this company.';
                $this->logContextChange($user, null, $companyId, 'access_denied');
                return $result;
            }

            // Clear company context if null
            if (!$companyId) {
                $this->clearCompanyContext($user, $request);
                $result['success'] = true;
                $result['message'] = 'Company context cleared.';
                $this->logContextChange($user, $result['previous_company'], null, 'cleared');
                return $result;
            }

            // Set the new company context
            $company = $this->setActiveCompany($user, $companyId, $request);
            
            if ($company) {
                $result['success'] = true;
                $result['message'] = "Switched to {$company['name']} successfully.";
                $result['company'] = $company;
                $this->logContextChange($user, $result['previous_company'], $companyId, 'switched');
            } else {
                $result['message'] = 'Failed to switch to the specified company.';
                $this->logContextChange($user, $result['previous_company'], $companyId, 'failed');
            }

        } catch (\Exception $e) {
            $result['message'] = 'An error occurred while switching companies.';
            $this->logError('Company switch failed', [
                'user_id' => $user->id,
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }

    /**
     * Get the active company ID for a user.
     */
    public function getActiveCompanyId(?User $user = null, ?Request $request = null): ?string
    {
        $company = $this->getActiveCompany($user, $request);
        return $company['id'] ?? null;
    }

    /**
     * Check if a user can access a specific company.
     */
    public function canUserAccessCompany(User $user, string $companyId): bool
    {
        // System users can access any company
        if ($user->isSystemUser()) {
            return Company::where('id', $companyId)->exists();
        }

        // Regular users need active company membership
        return $user->companies()
                   ->where('company_id', $companyId)
                   ->wherePivot('is_active', true)
                   ->exists();
    }

    /**
     * Refresh company context cache for a user.
     */
    public function refreshUserCompanyCache(User $user): void
    {
        Cache::forget($this->contextCacheKey($user));
        Cache::forget($this->userCompaniesCacheKey($user));
        $this->logDebug('Company cache refreshed', ['user_id' => $user->id]);
    }

    /**
     * Get debug information about company context.
     */
    public function getDebugInfo(?User $user = null, ?Request $request = null): array
    {
        if (!$user) {
            return [
                'user' => null,
                'resolution_steps' => ['No user provided'],
            ];
        }

        $debug = [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'resolution_steps' => [],
            'cache_keys' => [
                $this->contextCacheKey($user),
                $this->userCompaniesCacheKey($user),
            ],
        ];

        // Step through resolution process for debugging
        foreach (self::RESOLUTION_PRIORITY as $step) {
            $debug['resolution_steps'][$step] = $this->debugResolutionStep($step, $user, $request);
        }

        $debug['final_company'] = $this->getActiveCompany($user, $request);
        $debug['user_companies'] = $this->getUserCompanies($user);

        return $debug;
    }

    /**
     * Resolve the active company using priority order.
     */
    private function resolveActiveCompany(User $user, ?Request $request = null): ?array
    {
        $this->logDebug('Starting company resolution', [
            'user_id' => $user->id,
            'request_url' => $request?->fullUrl(),
        ]);

        foreach (self::RESOLUTION_PRIORITY as $method) {
            $company = $this->tryResolveByMethod($method, $user, $request);
            
            if ($company) {
                $this->logDebug('Company resolved', [
                    'method' => $method,
                    'company_id' => $company['id'],
                    'company_name' => $company['name'],
                ]);
                return $company;
            }
        }

        $this->logDebug('No company resolved for user', ['user_id' => $user->id]);
        return null;
    }

    /**
     * Try to resolve company by a specific method.
     */
    private function tryResolveByMethod(string $method, User $user, ?Request $request = null): ?array
    {
        switch ($method) {
            case 'route':
                return $this->resolveFromRoute($request);
            
            case 'session':
                return $this->resolveFromSession($user, $request);
            
            case 'database':
                return $this->resolveFromDatabase($user);
            
            case 'first':
                return $this->resolveFirstAvailable($user);
            
            default:
                return null;
        }
    }

    /**
     * Resolve company from route parameter.
     */
    private function resolveFromRoute(?Request $request = null): ?array
    {
        if (!$request) {
            return null;
        }

        $companyFromRoute = $request->route('company');
        
        if ($companyFromRoute instanceof Company) {
            return $this->formatCompanyData($companyFromRoute);
        }
        
        if (is_string($companyFromRoute) && Str::isUuid($companyFromRoute)) {
            $company = Company::find($companyFromRoute);
            return $company ? $this->formatCompanyData($company) : null;
        }

        return null;
    }

    /**
     * Resolve company from session.
     */
    private function resolveFromSession(User $user, ?Request $request = null): ?array
    {
        $sessionCompanyId = null;
        
        if ($request && $request->hasSession()) {
            $sessionCompanyId = $request->session()->get('active_company_id');
        } else {
            $sessionCompanyId = session('active_company_id');
        }

        if (!$sessionCompanyId || !Str::isUuid($sessionCompanyId)) {
            return null;
        }

        // Verify user has access to this company
        if (!$this->canUserAccessCompany($user, $sessionCompanyId)) {
            // Clear invalid session data
            if ($request && $request->hasSession()) {
                $request->session()->forget('active_company_id');
            } else {
                session()->forget('active_company_id');
            }
            return null;
        }

        $company = Company::find($sessionCompanyId);
        return $company ? $this->formatCompanyData($company, $user) : null;
    }

    /**
     * Resolve company from user's preferred company in database.
     */
    private function resolveFromDatabase(User $user): ?array
    {
        if (!$user->preferred_company_id) {
            return null;
        }

        // Verify user still has access to their preferred company
        if (!$this->canUserAccessCompany($user, $user->preferred_company_id)) {
            // Clear invalid preferred company
            $user->setPreferredCompany(null);
            return null;
        }

        $company = Company::find($user->preferred_company_id);
        return $company ? $this->formatCompanyData($company, $user) : null;
    }

    /**
     * Resolve the first available company for the user.
     */
    private function resolveFirstAvailable(User $user): ?array
    {
        $company = $user->companies()
                       ->wherePivot('is_active', true)
                       ->orderBy('name')
                       ->first();

        return $company ? $this->formatCompanyData($company, $user) : null;
    }

    /**
     * Fetch all companies for a user with enhanced data.
     */
    private function fetchUserCompanies(User $user): array
    {
        $companies = $user->companies()
                         ->select('companies.*')
                         ->selectRaw('company_user.role as user_role')
                         ->selectRaw('company_user.is_active as is_user_active')
                         ->selectRaw('company_user.joined_at')
                         ->orderByRaw('CASE WHEN company_user.is_active = true THEN 0 ELSE 1 END')
                         ->orderBy('companies.name')
                         ->get();

        return $companies->map(function ($company) use ($user) {
            return $this->formatCompanyData($company, $user, [
                'user_role' => $company->user_role,
                'is_user_active' => (bool) $company->is_user_active,
                'joined_at' => $company->joined_at,
            ]);
        })->toArray();
    }

    /**
     * Set the active company context.
     */
    private function setActiveCompany(User $user, string $companyId, ?Request $request = null): ?array
    {
        // Set in session
        if ($request && $request->hasSession()) {
            $request->session()->put('active_company_id', $companyId);
        } else {
            session(['active_company_id' => $companyId]);
        }

        // Save to user's preferred company in database
        try {
            $user->setPreferredCompany($companyId);
            $this->logDebug('Preferred company updated in database', [
                'user_id' => $user->id,
                'company_id' => $companyId,
            ]);
        } catch (\Exception $e) {
            // Log error but don't fail the operation since session is set
            $this->logError('Failed to update preferred company in database', [
                'user_id' => $user->id,
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);
        }

        // Clear cache
        $this->refreshUserCompanyCache($user);

        // Return the company data
        $company = Company::find($companyId);
        return $company ? $this->formatCompanyData($company, $user) : null;
    }

    /**
     * Clear company context.
     */
    private function clearCompanyContext(User $user, ?Request $request = null): void
    {
        // Clear from session
        if ($request && $request->hasSession()) {
            $request->session()->forget('active_company_id');
        } else {
            session()->forget('active_company_id');
        }

        // Clear preferred company from database
        try {
            $user->setPreferredCompany(null);
            $this->logDebug('Preferred company cleared from database', [
                'user_id' => $user->id,
            ]);
        } catch (\Exception $e) {
            // Log error but don't fail the operation since session is cleared
            $this->logError('Failed to clear preferred company from database', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Clear cache
        $this->refreshUserCompanyCache($user);
    }

    /**
     * Format company data for consistent output.
     */
    private function formatCompanyData(Company $company, ?User $user = null, array $context = []): array
    {
        $data = [
            'id' => $company->id,
            'name' => $company->name,
            'industry' => $company->industry,
            'country' => $company->country,
            'base_currency' => $company->base_currency,
            'created_at' => $company->created_at,
            'is_active' => $company->is_active,
        ];

        if ($user) {
            // Add user-specific context
            $data['user_role'] = $context['user_role'] ?? null;
            $data['is_user_active'] = $context['is_user_active'] ?? true;
            $data['joined_at'] = $context['joined_at'] ?? null;
            $data['can_switch_to'] = $this->canUserAccessCompany($user, $company->id);
        }

        return $data;
    }

    /**
     * Debug a specific resolution step.
     */
    private function debugResolutionStep(string $step, User $user, ?Request $request = null): array
    {
        switch ($step) {
            case 'route':
                $routeCompany = $request?->route('company');
                return [
                    'available' => $routeCompany !== null,
                    'type' => $routeCompany ? get_class($routeCompany) : null,
                    'valid' => $routeCompany instanceof Company || (is_string($routeCompany) && Str::isUuid($routeCompany)),
                ];

            case 'session':
                $sessionId = $request?->session()?->get('active_company_id') ?? session('active_company_id');
                return [
                    'company_id' => $sessionId,
                    'valid_uuid' => $sessionId ? Str::isUuid($sessionId) : false,
                    'user_has_access' => $sessionId ? $this->canUserAccessCompany($user, $sessionId) : false,
                ];

            case 'database':
                return [
                    'preferred_company_id' => $user->preferred_company_id,
                    'valid_uuid' => $user->preferred_company_id ? Str::isUuid($user->preferred_company_id) : false,
                    'user_has_access' => $user->preferred_company_id ? $this->canUserAccessCompany($user, $user->preferred_company_id) : false,
                    'company_exists' => $user->preferred_company_id ? Company::where('id', $user->preferred_company_id)->exists() : false,
                ];

            case 'first':
                $firstCompany = $user->companies()->wherePivot('is_active', true)->first();
                return [
                    'available' => $firstCompany !== null,
                    'company_id' => $firstCompany?->id,
                    'company_name' => $firstCompany?->name,
                ];

            default:
                return ['error' => 'Unknown resolution step'];
        }
    }

    /**
     * Log context change events.
     */
    private function logContextChange(User $user, ?string $fromCompanyId, ?string $toCompanyId, string $action): void
    {
        Log::info('Company context changed', [
            'action' => $action,
            'user_id' => $user->id,
            'from_company_id' => $fromCompanyId,
            'to_company_id' => $toCompanyId,
            'timestamp' => now()->toISOString(),
            'ip' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }

    /**
     * Log debug information.
     */
    private function logDebug(string $message, array $context = []): void
    {
        Log::debug('[CompanyContext] ' . $message, $context);
    }

    /**
     * Log error information.
     */
    private function logError(string $message, array $context = []): void
    {
        Log::error('[CompanyContext] ' . $message, $context);
    }
}
