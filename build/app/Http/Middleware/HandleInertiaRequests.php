<?php

namespace App\Http\Middleware;

use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Account;
use App\Modules\FuelStation\Services\FuelNavigationAccess;
use App\Services\CurrentCompany;
use Illuminate\Database\Eloquent\Model;
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

            // Support both Eloquent models and stdClass rows from the query builder.
            $data = $company instanceof Model
                ? $company->toArray()
                : (is_array($company) ? $company : get_object_vars($company));
            $settings = $data['settings'] ?? null;
            if (is_string($settings)) {
                $settings = json_decode($settings, true);
            }

            return [
                'id' => $data['id'] ?? null,
                'name' => $data['name'] ?? null,
                'slug' => $data['slug'] ?? null,
                'base_currency' => $data['base_currency'] ?? null,
                'logo_url' => $data['logo_url'] ?? null,
                'industry' => $data['industry'] ?? null,
                'industry_code' => $data['industry_code'] ?? null,
                'settings' => is_array($settings) ? $settings : null,
                'onboarding_completed' => $data['onboarding_completed'] ?? null,
            ];
        };

        /*
         * Deferred on purpose — do not make this eager again.
         *
         * Inertia calls share() BEFORE $next($request), so anything computed here runs
         * before CheckFirstTimeUser and IdentifyCompany have set app.current_user_id.
         * Under enforced row level security the auth.companies policy then matches on
         * nothing: not super admin, no company context, no user id. The query comes back
         * empty, so the switcher renders blank and every module nav bails out on the
         * missing slug — the whole sidebar disappears.
         *
         * It only ever showed up in production. A superuser connection bypasses RLS
         * entirely, so in local development the same query returns rows and the menu looks
         * fine. Worse, app.current_user_id set with is_local=false survives on a pooled
         * connection, so a request could read the GUC left behind by the PREVIOUS request
         * and show whichever companies that user could see.
         *
         * Resolving inside a closure defers it to render time, after the middleware that
         * establishes the tenant context. fuelNavigation below was already written this
         * way for the same reason.
         */
        $resolved = null;
        $resolve = function () use ($request, &$resolved): array {
            if ($resolved !== null) {
                return $resolved;
            }

            $isGodMode = $request->user()?->isGodMode() ?? false;
            $currentCompany = CompanyContext::getCompany();

            // God-mode users can enter any active company without membership.
            $companies = $request->user()
                ? ($isGodMode
                    ? \DB::table('auth.companies as c')
                        ->where('c.is_active', true)
                        ->select('c.id', 'c.name', 'c.slug', 'c.base_currency', 'c.logo_url', 'c.industry', 'c.industry_code', 'c.settings', 'c.onboarding_completed')
                        ->orderBy('c.name')
                        ->get()
                    : \DB::table('auth.company_user as cu')
                        ->join('auth.companies as c', 'cu.company_id', '=', 'c.id')
                        ->where('cu.user_id', $request->user()->id)
                        ->where('cu.is_active', true)
                        ->where('c.is_active', true)
                        ->select('c.id', 'c.name', 'c.slug', 'c.base_currency', 'c.logo_url', 'c.industry', 'c.industry_code', 'c.settings', 'c.onboarding_completed')
                        ->orderBy('c.name')
                        ->get())
                : collect();

            // On a global route there is no company in context; show the last one visited.
            if (! $currentCompany && $request->user()) {
                $rememberedSlug = session('last_company_slug');
                $currentCompany = $companies->firstWhere('slug', $rememberedSlug) ?: $companies->first();

                if ($currentCompany && $currentCompany->slug !== $rememberedSlug) {
                    session(['last_company_slug' => $currentCompany->slug]);
                }
            }

            $role = null;
            if ($currentCompany && $request->user()) {
                $role = $isGodMode
                    ? 'super_admin'
                    : \DB::table('auth.company_user')
                        ->where('company_id', $currentCompany->id)
                        ->where('user_id', $request->user()->id)
                        ->where('is_active', true)
                        ->value('role');
            }

            return $resolved = [
                'company' => $currentCompany,
                'companies' => $companies,
                'role' => $role,
            ];
        };

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
                'currentCompany' => fn () => $serializeCompany($resolve()['company']),
                'currentCompanyRole' => fn () => $resolve()['role'],
                'fuelNavigation' => function () use ($request) {
                    // Resolve after IdentifyCompany has run. The display fallback above
                    // can be a stdClass from a different, previously visited company.
                    $company = app(CurrentCompany::class)->get();
                    $user = $request->user();
                    if (! $company || ! $user) {
                        return null;
                    }

                    $isFuelStation = $company->isModuleEnabled('fuel_station')
                        || $company->industry_code === 'fuel_station'
                        || $company->industry === 'fuel_station';

                    return $isFuelStation
                        ? app(FuelNavigationAccess::class)->forUser($company, $user)
                        : null;
                },
                'companies' => fn () => $resolve()['companies']->map(fn ($c) => $serializeCompany($c))->values(),
                'canCreateCompanies' => $request->user() !== null,
                'openingBalance' => function () use ($request) {
                    // Only on company pages, where IdentifyCompany has set the company context.
                    // Elsewhere (home, the companies list) CurrentCompany can still return a
                    // display fallback, and the account lookup below would read company data
                    // with no context set. Quick Add only appears on company pages anyway.
                    if (! $request->route('company')) {
                        return null;
                    }
                    $company = app(CurrentCompany::class)->get();
                    $user = $request->user();
                    if (! $company || ! $user) {
                        return null;
                    }

                    $opening = ($company->settings ?? [])['opening_balances'] ?? [];

                    return [
                        'canManage' => $user->hasCompanyPermission(Permissions::OPENING_BALANCE_MANAGE),
                        'asOfDate' => $opening['as_of_date'] ?? null,
                        'locked' => ! empty($opening['locked_at']),
                        'hasAmanat' => Account::where('company_id', $company->id)
                            ->whereNull('deleted_at')
                            ->where('is_active', true)
                            ->where('code', '2200')
                            ->exists(),
                    ];
                },
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => [
                'uploadedLogoUrl' => fn () => $request->session()->get('uploaded_logo_url'),
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'tank' => fn () => $request->session()->get('tank'),
                'item' => fn () => $request->session()->get('item'),
                'umrahImportedMutamers' => fn () => $request->session()->get('umrah_imported_mutamers'),
            ],
        ];
    }
}
