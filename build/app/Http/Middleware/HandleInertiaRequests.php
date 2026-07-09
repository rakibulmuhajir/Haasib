<?php

namespace App\Http\Middleware;

use App\Facades\CompanyContext;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');
        $currentCompany = CompanyContext::getCompany();

        $serializeCompany = static function ($company): ?array {
            if (! $company) {
                return null;
            }

            // Support both Eloquent models and stdClass from query builder.
            $data = is_array($company) ? $company : (array) $company;

            return [
                'id' => $data['id'] ?? null,
                'name' => $data['name'] ?? null,
                'slug' => $data['slug'] ?? null,
                'base_currency' => $data['base_currency'] ?? null,
                'industry' => $data['industry'] ?? null,
                'industry_code' => $data['industry_code'] ?? null,
                'settings' => $data['settings'] ?? null,
                'onboarding_completed' => $data['onboarding_completed'] ?? null,
            ];
        };

        $isGodMode = $request->user()?->isGodMode() ?? false;

        // Get user's companies. God-mode users can enter any active company without membership.
        $companies = $request->user()
            ? ($isGodMode
                ? \DB::table('auth.companies as c')
                    ->where('c.is_active', true)
                    ->select('c.id', 'c.name', 'c.slug', 'c.base_currency', 'c.industry', 'c.industry_code', 'c.settings', 'c.onboarding_completed')
                    ->orderBy('c.name')
                    ->get()
                : \DB::table('auth.company_user as cu')
                    ->join('auth.companies as c', 'cu.company_id', '=', 'c.id')
                    ->where('cu.user_id', $request->user()->id)
                    ->where('cu.is_active', true)
                    ->where('c.is_active', true)
                    ->select('c.id', 'c.name', 'c.slug', 'c.base_currency', 'c.industry', 'c.industry_code', 'c.settings', 'c.onboarding_completed')
                    ->orderBy('c.name')
                    ->get())
            : collect();

        // If no current company (on global routes), use last accessed company for display
        if (! $currentCompany && $request->user()) {
            $rememberedSlug = session('last_company_slug');
            $currentCompany = $companies->firstWhere('slug', $rememberedSlug) ?: $companies->first();

            if ($currentCompany && $currentCompany->slug !== $rememberedSlug) {
                session(['last_company_slug' => $currentCompany->slug]);
            }
        }

        // Current company role for the authenticated user (used for mode gating)
        $currentCompanyRole = null;
        if ($currentCompany && $request->user()) {
            $currentCompanyRole = $isGodMode
                ? 'super_admin'
                : \DB::table('auth.company_user')
                    ->where('company_id', $currentCompany->id)
                    ->where('user_id', $request->user()->id)
                    ->where('is_active', true)
                    ->value('role');
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
                'currentCompany' => $serializeCompany($currentCompany),
                'currentCompanyRole' => $currentCompanyRole,
                'companies' => $companies->map(fn ($c) => $serializeCompany($c))->values(),
                'canCreateCompanies' => $isGodMode,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'tank' => fn () => $request->session()->get('tank'),
                'item' => fn () => $request->session()->get('item'),
            ],
        ];
    }
}
