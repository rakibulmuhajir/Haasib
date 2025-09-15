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

        \Log::debug('[HandleInertiaRequests] Request has session: '.($hasSession ? 'yes' : 'no'));
        \Log::debug('[HandleInertiaRequests] Session ID: '.($hasSession ? $request->session()->getId() : 'none'));
        \Log::debug('[HandleInertiaRequests] Session data before access: ', $hasSession ? $request->session()->all() : []);
        \Log::debug('[HandleInertiaRequests] Retrieved company ID from session: '.($companyId ?: 'null'));

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

        // Only set fallback to first company if no session company exists and user has companies
        if (! $companyId && $request->user()) {
            $firstCompany = $request->user()->companies()->first();

            if ($firstCompany) {
                $companyId = $firstCompany->id;
                \Log::debug('[HandleInertiaRequests] Set fallback company ID: '.$companyId);
                $request->session()->put('current_company_id', $companyId);
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

            'auth' => [
                'user' => $request->user() ? $request->user()->load('companies') : null,
                'companyId' => $companyId,
                'currentCompany' => $request->user() ? $request->user()->currentCompany : null,
                'isSuperAdmin' => (bool) optional($request->user())->isSuperAdmin(),
                'permissions' => $request->user() ? [
                    'ledger.view' => $request->user()->can('ledger.view'),
                    'ledger.create' => $request->user()->can('ledger.create'),
                    'ledger.post' => $request->user()->can('ledger.post'),
                    'ledger.void' => $request->user()->can('ledger.void'),
                    'ledger.accounts.view' => $request->user()->can('ledger.accounts.view'),
                ] : [],
            ],

            // Expose CSRF token so the SPA can update its meta tag after
            // session regeneration (e.g., after login/logout) to avoid 419s.
            'csrf_token' => csrf_token(),

            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
        ];
    }
}
