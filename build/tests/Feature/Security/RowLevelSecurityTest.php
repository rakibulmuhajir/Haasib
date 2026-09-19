<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Customer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * These tests connect as the application's own least-privilege role, not as the
 * superuser the suite normally runs under, because a superuser bypasses row
 * level security unconditionally and would prove nothing.
 */
const APP_ROLE = 'haasib_app';

function rlsCompany(string $label): Company
{
    return Company::create([
        'name' => 'RLS '.$label.' '.str()->random(6),
        'slug' => 'rls-'.str()->lower(str()->random(12)),
        'base_currency' => 'PKR',
    ]);
}

function rlsCustomer(Company $company, string $name): Customer
{
    DB::select("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);

    return Customer::create([
        'company_id' => $company->id,
        'customer_number' => 'C-'.str()->upper(str()->random(8)),
        'name' => $name,
        'customer_type' => 'individual',
        'currency' => 'PKR',
    ]);
}

function asAppRole(callable $callback): mixed
{
    DB::statement('SET LOCAL ROLE '.APP_ROLE);

    try {
        return $callback();
    } finally {
        DB::statement('RESET ROLE');
    }
}

function setCompanyContext(?string $companyId): void
{
    DB::select("SELECT set_config('app.current_company_id', ?, false)", [$companyId ?? '']);
}

beforeEach(function () {
    $role = DB::selectOne('SELECT rolsuper, rolbypassrls FROM pg_roles WHERE rolname = ?', [APP_ROLE]);

    if ($role === null) {
        $this->markTestSkipped('The '.APP_ROLE.' role is not provisioned in this database.');
    }

    // Both the grants and the FORCE flags come from the migrations gated
    // behind RLS_ENFORCEMENT. Without them the role cannot read a single
    // table, and what these tests would be measuring is the absence of a
    // GRANT rather than the presence of isolation. Run the suite with
    // RLS_ENFORCEMENT=on to exercise them.
    $granted = DB::selectOne(
        'SELECT has_table_privilege(?, ?, ?) AS granted',
        [APP_ROLE, 'acct.customers', 'SELECT']
    );

    if ($granted === null || ! $granted->granted) {
        $this->markTestSkipped('Row level security enforcement is not applied here; run with RLS_ENFORCEMENT=on.');
    }

    expect((bool) $role->rolsuper)->toBeFalse();
    expect((bool) $role->rolbypassrls)->toBeFalse();
});

test('the application role is neither a superuser nor able to bypass rls, and owns no unforced table', function () {
    $owned = DB::selectOne(
        "SELECT count(*) AS n FROM pg_class c JOIN pg_roles r ON r.oid = c.relowner
         WHERE r.rolname = ? AND c.relkind = 'r'",
        [APP_ROLE]
    );

    // Where the role does own tables, FORCE ROW LEVEL SECURITY must close the
    // owner bypass -- so assert there is no owned table left unforced.
    $unforced = DB::selectOne(
        "SELECT count(*) AS n FROM pg_class c JOIN pg_roles r ON r.oid = c.relowner
         WHERE r.rolname = ? AND c.relkind = 'r' AND c.relrowsecurity AND NOT c.relforcerowsecurity",
        [APP_ROLE]
    );

    expect((int) $unforced->n)->toBe(0);
    expect((int) $owned->n)->toBeGreaterThanOrEqual(0);
});

test('every table with row level security enabled also forces it', function () {
    $rows = DB::select(
        "SELECT format('%I.%I', n.nspname, c.relname) AS ident
         FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
         WHERE c.relkind = 'r' AND c.relrowsecurity AND NOT c.relforcerowsecurity"
    );

    expect(array_map(fn ($r) => $r->ident, $rows))->toBe([]);
});

test('no policy casts the tenant guc without a null guard', function () {
    $rows = DB::select(<<<'SQL'
        SELECT format('%s.%s (%s)', n.nspname, c.relname, p.polname) AS ident
        FROM pg_policy p
        JOIN pg_class c ON c.oid = p.polrelid
        JOIN pg_namespace n ON n.oid = c.relnamespace
        WHERE (coalesce(pg_get_expr(p.polqual, p.polrelid), '') || coalesce(pg_get_expr(p.polwithcheck, p.polrelid), ''))
              ~ 'current_setting\('
          AND (coalesce(pg_get_expr(p.polqual, p.polrelid), '') || coalesce(pg_get_expr(p.polwithcheck, p.polrelid), ''))
              !~ 'NULLIF\(current_setting'
    SQL);

    expect(array_map(fn ($r) => $r->ident, $rows))->toBe([]);
});

test('as the application role a company sees only its own rows', function () {
    $a = rlsCompany('A');
    $b = rlsCompany('B');
    rlsCustomer($a, 'Alpha customer');
    rlsCustomer($b, 'Bravo customer');

    setCompanyContext($a->id);
    $names = asAppRole(fn () => DB::table('acct.customers')->pluck('name')->all());

    expect($names)->toBe(['Alpha customer']);

    setCompanyContext($b->id);
    $names = asAppRole(fn () => DB::table('acct.customers')->pluck('name')->all());

    expect($names)->toBe(['Bravo customer']);
});

test('as the application role a missing company context returns nothing rather than everything', function () {
    $a = rlsCompany('A');
    rlsCustomer($a, 'Alpha customer');

    DB::statement('RESET app.current_company_id');

    // The GUC now reads as the empty string, which is the case that used to
    // raise "invalid input syntax for type uuid".
    expect(DB::selectOne("SELECT current_setting('app.current_company_id', true) AS v")->v)->toBe('');

    // Reading with no company context is the assertion, not an accident.
    $count = app(\App\Support\Database\TenantContextGuard::class)
        ->ignoring(fn () => asAppRole(fn () => DB::table('acct.customers')->count()));

    expect($count)->toBe(0);
});

test('as the application role a write into another company is rejected', function () {
    $a = rlsCompany('A');
    $b = rlsCompany('B');

    setCompanyContext($a->id);

    expect(fn () => asAppRole(fn () => DB::table('acct.customers')->insert([
        'id' => (string) str()->uuid(),
        'company_id' => $b->id,
        'customer_number' => 'C-'.str()->upper(str()->random(8)),
        'name' => 'Smuggled',
        'customer_type' => 'individual',
        'currency' => 'PKR',
        'created_at' => now(),
        'updated_at' => now(),
    ])))->toThrow(QueryException::class);
});

test('as the application role a company is visible to its own members only', function () {
    $user = User::factory()->create();
    $outsider = User::factory()->create();
    $a = rlsCompany('A');
    rlsCompany('B');

    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$user->id]);

    DB::table('auth.company_user')->insert([
        'company_id' => $a->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'joined_at' => now(),
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::statement('RESET app.current_company_id');
    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$user->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");

    $visible = asAppRole(fn () => DB::table('auth.companies')->pluck('id')->all());
    expect($visible)->toBe([$a->id]);

    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$outsider->id]);
    $visible = asAppRole(fn () => DB::table('auth.companies')->pluck('id')->all());
    expect($visible)->toBe([]);
});
