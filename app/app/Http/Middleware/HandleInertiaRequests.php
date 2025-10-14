<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        // pull from session if available
        $hasSession = $request->hasSession();
        $companyId = $hasSession ? $request->session()->get('current_company_id') : null;

        // Check if super admin is intentionally in global view mode
        $isGlobalView = $hasSession ? $request->session()->get('super_admin_global_view', false) : false;

        \Log::debug('[HandleInertiaRequests] Request has session: '.($hasSession ? 'yes' : 'no'));
        \Log::debug('[HandleInertiaRequests] Session ID: '.($hasSession ? $request->session()->getId() : 'none'));
        \Log::debug('[HandleInertiaRequests] Session data before access: ', $hasSession ? $request->session()->all() : []);
        \Log::debug('[HandleInertiaRequests] Retrieved company ID from session: '.($companyId ?: 'null'));
        \Log::debug('[HandleInertiaRequests] Is global view: '.($isGlobalView ? 'yes' : 'no'));

        // Validate that the user has access to the session company
        if ($companyId && $request->user()) {
            // Super admins can access any company
            if ($request->user()->isSuperAdmin()) {
                $hasAccess = true;
            } else {
                $hasAccess = $request->user()->companies()
                    ->where('auth.companies.id', $companyId)
                    ->exists();
            }

            if (! $hasAccess) {
                \Log::debug('[HandleInertiaRequests] User does not have access to session company, clearing it');
                $request->session()->remove('current_company_id');
                $companyId = null;
            }
        }

        // Set fallback company if no session company exists and user has companies
        // But NOT if super admin is intentionally in global view mode
        if (! $companyId && $request->user() && ! $isGlobalView) {
            $firstCompany = $request->user()->companies()->first();

            if (! $firstCompany && $request->user()->isSuperAdmin()) {
                // Super admins can use any company, get the first one
                $firstCompany = \App\Models\Company::first();
            }

            if ($firstCompany) {
                $companyId = $firstCompany->id;
                \Log::debug('[HandleInertiaRequests] Set fallback company ID: '.$companyId);
                $request->session()->put('current_company_id', $companyId);
                // Ensure session is saved immediately
                $request->session()->save();
            }
        }

        // Debug logging
        \Log::debug('[HandleInertiaRequests] Session company ID: '.($companyId ?: 'null'));
        \Log::debug('[HandleInertiaRequests] Session data: ', $request->session()->all());

        // Check user's currentCompany method
        if ($request->user()) {
            $currentCompany = $request->user()->currentCompany;
            \Log::debug('[HandleInertiaRequests] User currentCompany: '.($currentCompany ? $currentCompany->name.' ('.$currentCompany->id.')' : 'null'));
        }

        return [
            ...parent::share($request),

            'auth' => array_merge([
                'user' => $request->user() ? $request->user()->load('companies') : null,
                'companyId' => $companyId,
                'currentCompany' => $request->user() ? $request->user()->currentCompany : null,
                'isSuperAdmin' => (bool) optional($request->user())->isSuperAdmin(),
            ], $request->user() ? $this->sharePermissions($request) : ['permissions' => [], 'roles' => [], 'canManageCompany' => false]),

            // Expose CSRF token so the SPA can update its meta tag after
            // session regeneration (e.g., after login/logout) to avoid 419s.
            'csrf_token' => csrf_token(),

            // Share flash messages and clear them after sharing
            'flash' => function () use ($request) {
                return [
                    'success' => $request->session()->pull('success'),
                    'error' => $request->session()->pull('error'),
                ];
            },

            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],

            'appLocale' => $this->formatLocale(app()->getLocale()),
            'supportedLocales' => ['en-US', 'fr-FR'],
            'userLocale' => $this->formatLocale(optional($request->user())->locale),
            'tenantLocale' => $this->formatLocale(optional(optional($request->user())->currentCompany)->locale),
        ];
    }

    /**
     * Share user permissions and roles with the frontend.
     */
    protected function sharePermissions(Request $request): array
    {
        $user = $request->user();
        $currentCompany = $user->currentCompany;

        // Snapshot current team context using Spatie's helper
        $previousTeamId = getPermissionsTeamId();

        try {
            // Explicitly clear team context to get true system-wide permissions
            setPermissionsTeamId(null);
            $systemPermissions = $user->getAllPermissions()->pluck('name');
            $systemRoles = $user->getRoleNames();

            // Get company-specific permissions by setting team context
            $companyPermissions = collect();
            $companyRoles = collect();
            $canManageCompany = false;

            if ($currentCompany) {
                // Set team context to get company-specific permissions
                setPermissionsTeamId($currentCompany->id);
                $companyPermissions = $user->getAllPermissions()->pluck('name');
                $companyRoles = $user->getRoleNames();

                // Check management permissions while in company context
                $canManageCompany = $user->hasRole('owner') || $user->hasRole('admin');
            }

            return [
                'permissions' => $systemPermissions,
                'companyPermissions' => $companyPermissions,
                'roles' => [
                    'system' => $systemRoles->toArray(),
                    'company' => $companyRoles->toArray(),
                ],
                'canManageCompany' => $canManageCompany,
                'currentCompanyId' => $currentCompany?->id,
            ];
        } finally {
            // Always restore original team context
            setPermissionsTeamId($previousTeamId);
        }
    }

    protected function formatLocale(?string $locale): ?string
    {
        if (! $locale) {
            return null;
        }

        $normalized = str_replace('_', '-', $locale);

        return match ($normalized) {
            'en' => 'en-US',
            'fr' => 'fr-FR',
            default => $normalized,
        };
    }
}
