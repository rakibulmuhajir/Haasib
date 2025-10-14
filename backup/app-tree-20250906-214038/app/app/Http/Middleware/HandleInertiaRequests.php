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
        // pull from session if available; otherwise use the bound tenant context
        $companyId =
            ($request->hasSession() ? $request->session()->get('current_company_id') : null)
            ?? (app()->bound('tenant.company_id') ? app('tenant.company_id') : null);

        return [
            ...parent::share($request),

            'auth' => [
                'user' => $request->user(),
                'companyId' => $companyId,
                'isSuperAdmin' => (bool) optional($request->user())->isSuperAdmin(),
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
