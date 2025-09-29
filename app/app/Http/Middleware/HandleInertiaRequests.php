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

        // \Log::debug('[HandleInertiaRequests] Request has session: '.($hasSession ? 'yes' : 'no'));
        // \Log::debug('[HandleInertiaRequests] Session ID: '.($hasSession ? $request->session()->getId() : 'none'));
        // \Log::debug('[HandleInertiaRequests] Session data before access: ', $hasSession ? $request->session()->all() : []);
        // \Log::debug('[HandleInertiaRequests] Retrieved company ID from session: '.($companyId ?: 'null'));

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
        // For superadmins without companies, use the first company in the system
        if (! $companyId && $request->user()) {
            $firstCompany = $request->user()->companies()->first();

            if (! $firstCompany && $request->user()->isSuperAdmin()) {
                // Super admins can use any company, get the first one
                $firstCompany = \App\Models\Company::first();
            }

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
                'can' => $request->user() ? [
                    'ledger' => [
                        'view' => $request->user()->can('ledger.view'),
                        'create' => $request->user()->can('ledger.create'),
                        'post' => $request->user()->can('ledger.post'),
                        'void' => $request->user()->can('ledger.void'),
                        'accounts' => [
                            'view' => $request->user()->can('ledger.accounts.view'),
                        ],
                    ],
                    'invoices' => [
                        'view' => $request->user()->can('invoices.view'),
                        'create' => $request->user()->can('invoices.create'),
                        'edit' => $request->user()->can('invoices.edit'),
                        'delete' => $request->user()->can('invoices.delete'),
                        'send' => $request->user()->can('invoices.send'),
                        'post' => $request->user()->can('invoices.post'),
                    ],
                    'payments' => [
                        'view' => $request->user()->can('payments.view'),
                        'create' => $request->user()->can('payments.create'),
                        'edit' => $request->user()->can('payments.edit'),
                        'delete' => $request->user()->can('payments.delete'),
                        'allocate' => $request->user()->can('payments.allocate'),
                    ],
                    'currency' => [
                        'view' => $request->user()->can('currency.view'),
                        'companyEdit' => $request->user()->can('currency.company.edit'),
                        'systemManage' => $request->user()->can('currency.system.manage'),
                        'exchangeEdit' => $request->user()->can('currency.exchange.edit'),
                        'defaultSet' => $request->user()->can('currency.default.set'),
                        'crud' => $request->user()->can('currency.crud'),
                    ],
                ] : null,
            ],

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
        ];
    }
}
