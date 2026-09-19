<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Tests\Concerns\RefreshApplicationDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Enter a company for the rest of the PostgreSQL session.
 *
 * Under enforced row level security a fixture that creates a company cannot
 * write any of that company's rows until the session is inside it: the write
 * is refused outright, and a read of them returns nothing while reporting
 * success. Application code enters the company it has just created; fixtures
 * have to do the same.
 */
function enterCompany(App\Models\Company|string $company): void
{
    Illuminate\Support\Facades\DB::select(
        "SELECT set_config('app.current_company_id', ?, false)",
        [is_string($company) ? $company : $company->id]
    );
}

/**
 * Write a company_user row from inside the company that owns it, and leave the
 * session in whatever company it was already in.
 *
 * A membership row is company-scoped like any other. A fixture that has just
 * built a second company is sitting in that second company, and under enforced
 * row level security the first company's membership row cannot be written from
 * there.
 */
function addCompanyMemberRow(App\Models\Company $company, App\Models\User $user, string $role): void
{
    $previous = (string) (Illuminate\Support\Facades\DB::selectOne(
        "SELECT current_setting('app.current_company_id', true) AS value"
    )->value ?? '');

    enterCompany($company);

    try {
        Illuminate\Support\Facades\DB::table('auth.company_user')->insert([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => $role,
            'joined_at' => now(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    } finally {
        Illuminate\Support\Facades\DB::select(
            "SELECT set_config('app.current_company_id', ?, false)",
            [$previous]
        );
    }
}

/**
 * Skip a test that needs more of the database than the application role has.
 *
 * The suite can be run as `haasib_app` -- the least-privilege role production
 * connects as -- to see what enforced row level security actually does. A
 * handful of tests exercise DDL or role creation, which that role cannot do
 * and which production would never ask of it either.
 */
function requiresPrivilegedDatabaseRole(string $reason): void
{
    $row = Illuminate\Support\Facades\DB::selectOne(
        'SELECT (rolsuper OR rolcreaterole) AS privileged FROM pg_roles WHERE rolname = current_user'
    );

    if ($row === null || ! $row->privileged) {
        test()->markTestSkipped($reason);
    }
}

function something()
{
    // ..
}
