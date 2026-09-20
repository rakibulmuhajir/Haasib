<?php

use App\Models\Company;
use App\Models\User;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;

/**
 * The GUCs that carry tenant context are session-scoped, not transaction-scoped, so they
 * outlive the request that set them on a pooled connection. Without an unconditional reset
 * at the front of the stack, a request that reads a company-scoped table before anything
 * establishes context reads the PREVIOUS request's tenant — which, on a shared connection,
 * may be another user entirely.
 *
 * That is the fail-open direction, and it cannot be caught by TenantContextGuard: its own
 * documentation says it answers "is there a tenant context", not "is it the correct one".
 * A stale context is present and wrong.
 *
 * These tests pin the invariant EstablishTenantContext exists to hold.
 */
function leakageUser(string $companyName): array
{
    $user = User::factory()->withoutTwoFactor()->create();
    $company = Company::create([
        'name' => $companyName,
        'slug' => str()->slug($companyName).'-'.str()->lower(str()->random(6)),
        'base_currency' => 'PKR',
    ]);

    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    DB::select("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
    app(CompanyRbacBootstrapper::class)->bootstrap($company);
    DB::table('auth.company_user')->insert([
        'company_id' => $company->id, 'user_id' => $user->id, 'role' => 'owner',
        'joined_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    app(CompanyContextService::class)->withContext(
        $company,
        fn () => app(CompanyContextService::class)->assignRole($user, 'owner')
    );
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");

    return compact('user', 'company');
}

test('a request never inherits the tenant context of the request before it', function () {
    $first = leakageUser('First Tenant Co');
    $second = leakageUser('Second Tenant Co');

    // Stand in for a pooled connection still carrying the previous request's identity.
    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$first['user']->id]);
    DB::select("SELECT set_config('app.current_company_id', ?, false)", [$first['company']->id]);

    $response = test()->actingAs($second['user'])->followingRedirects()->get('/dashboard');
    $response->assertSuccessful();

    // Whatever the page shows, it must belong to the second user — never the first.
    $companies = collect($response->viewData('page')['props']['auth']['companies'] ?? [])
        ->pluck('name')->all();

    expect($companies)->not->toContain('First Tenant Co');
});

test('the stale company context is cleared before anything can read it', function () {
    $first = leakageUser('Stale Company');
    $second = leakageUser('Fresh Company');

    DB::select("SELECT set_config('app.current_company_id', ?, false)", [$first['company']->id]);

    test()->actingAs($second['user'])->followingRedirects()->get('/dashboard')->assertSuccessful();

    // /dashboard is not company-scoped, so no company should have been adopted from the
    // leftover GUC. The only acceptable outcomes are empty or the second user's own.
    $adopted = DB::selectOne("SELECT current_setting('app.current_company_id', true) AS v")->v;
    expect($adopted)->not->toBe($first['company']->id);
});

test('a guest request clears the identity left by the request before it', function () {
    $stale = leakageUser('Someone Else Co');

    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$stale['user']->id]);

    // A public route, deliberately. On a route behind `auth` the guest is redirected by
    // route middleware, which Laravel runs before the web group's appended middleware, so
    // nothing in the group gets a chance to clear anything. That is acceptable: such a
    // request is redirected without reading a single tenant-scoped row. What matters is
    // that a request which DOES reach the application carries no borrowed identity.
    test()->get('/login')->assertSuccessful();

    $uid = DB::selectOne("SELECT current_setting('app.current_user_id', true) AS v")->v;
    expect($uid)->toBeIn([null, '']);
});

test('an authenticated request has its user identity set before the page renders', function () {
    $f = leakageUser('Context Co');

    DB::statement('RESET app.current_user_id');

    test()->actingAs($f['user'])->followingRedirects()->get('/dashboard')->assertSuccessful();

    // Set by EstablishTenantContext, which runs ahead of Inertia's shared data — this is
    // what stopped the company switcher and sidebar coming back empty under enforced RLS.
    $uid = DB::selectOne("SELECT current_setting('app.current_user_id', true) AS v")->v;
    expect($uid)->toBe($f['user']->id);
});
