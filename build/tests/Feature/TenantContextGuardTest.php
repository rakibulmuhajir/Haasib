<?php

use App\Support\Database\MissingCompanyContextException;
use App\Support\Database\TenantContextGuard;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

/**
 * The guard's job is to make the *silent* half of row level security loud: a
 * read with no company context returns nothing and reports success, which
 * looks exactly like an empty table.
 */
function guardEvent(string $sql, array $bindings = []): QueryExecuted
{
    return new QueryExecuted($sql, $bindings, 0.1, DB::connection());
}

function tenantGuard(string $mode = TenantContextGuard::MODE_THROW): TenantContextGuard
{
    return new TenantContextGuard($mode, [DB::connection()->getName()]);
}

function withoutCompanyContext(callable $callback): mixed
{
    DB::statement('RESET app.current_company_id');
    DB::statement('RESET app.is_super_admin');

    try {
        return $callback();
    } finally {
        DB::statement('RESET app.is_super_admin');
    }
}

test('it raises when a company scoped table is read with no company context', function () {
    withoutCompanyContext(function () {
        $guard = tenantGuard();

        expect(fn () => $guard->handle(guardEvent('select * from "acct"."fiscal_years"')))
            ->toThrow(MissingCompanyContextException::class);
    });
});

test('it names the table and the query it caught', function () {
    withoutCompanyContext(function () {
        $guard = tenantGuard();

        try {
            $guard->handle(guardEvent('select * from "acct"."fiscal_years" where "status" = ?', ['open']));
            $this->fail('Expected the guard to raise.');
        } catch (MissingCompanyContextException $e) {
            expect($e->getMessage())
                ->toContain('acct.fiscal_years')
                ->toContain('select * from "acct"."fiscal_years"');
        }
    });
});

test('it stays quiet once a company context is set', function () {
    withoutCompanyContext(function () {
        $guard = tenantGuard();

        $companyId = '01a0b5c4-0000-7000-8000-00000000000a';
        $guard->handle(guardEvent("select set_config('app.current_company_id', ?, false)", [$companyId]));

        $guard->handle(guardEvent('select * from "acct"."fiscal_years"'));
    });

    expect(true)->toBeTrue();
});

test('it stays quiet for genuinely cross company work', function () {
    withoutCompanyContext(function () {
        $guard = tenantGuard();

        // What CompanyContextService::crossCompany() does.
        DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
        $guard->handle(guardEvent("select set_config('app.is_super_admin', 'true', false)"));

        $guard->handle(guardEvent('select * from "acct"."fiscal_years"'));
    });

    expect(true)->toBeTrue();
});

test('it ignores tables that carry no company scope', function () {
    withoutCompanyContext(function () {
        $guard = tenantGuard();

        $guard->handle(guardEvent('select * from "public"."currencies"'));
        $guard->handle(guardEvent('select * from "auth"."users"'));
        $guard->handle(guardEvent('select * from "migrations"'));
    });

    expect(true)->toBeTrue();
});

test('it catches a company scoped table reached through a join', function () {
    withoutCompanyContext(function () {
        $guard = tenantGuard();

        expect(fn () => $guard->handle(guardEvent(
            'select * from "public"."currencies" inner join "acct"."fiscal_years" on true'
        )))->toThrow(MissingCompanyContextException::class);
    });
});

test('it catches writes as well as reads', function () {
    withoutCompanyContext(function () {
        $guard = tenantGuard();

        expect(fn () => $guard->handle(guardEvent('insert into "acct"."fiscal_years" ("name") values (?)', ['FY'])))
            ->toThrow(MissingCompanyContextException::class);

        expect(fn () => $guard->handle(guardEvent('update "acct"."fiscal_years" set "status" = ?', ['closed'])))
            ->toThrow(MissingCompanyContextException::class);

        expect(fn () => $guard->handle(guardEvent('delete from "acct"."fiscal_years"')))
            ->toThrow(MissingCompanyContextException::class);
    });
});

test('it does nothing at all when switched off', function () {
    withoutCompanyContext(function () {
        $guard = tenantGuard(TenantContextGuard::MODE_OFF);

        expect($guard->enabled())->toBeFalse();

        $guard->handle(guardEvent('select * from "acct"."fiscal_years"'));
    });

    expect(true)->toBeTrue();
});

test('it leaves other connections alone', function () {
    withoutCompanyContext(function () {
        $guard = new TenantContextGuard(TenantContextGuard::MODE_THROW, ['some-other-connection']);

        $guard->handle(guardEvent('select * from "acct"."fiscal_years"'));
    });

    expect(true)->toBeTrue();
});

test('it believes the session over its own mirror', function () {
    withoutCompanyContext(function () {
        $guard = tenantGuard();

        // Context set by a path the guard never observed -- a raw PDO call, a
        // pooled connection handed over mid-flight. The mirror says "missing";
        // the session says otherwise, and the session wins.
        DB::select("SELECT set_config('app.current_company_id', ?, false)", ['01a0b5c4-0000-7000-8000-00000000000b']);

        $guard->handle(guardEvent('select * from "acct"."fiscal_years"'));

        DB::statement('RESET app.current_company_id');
    });

    expect(true)->toBeTrue();
});
