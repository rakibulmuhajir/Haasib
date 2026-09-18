<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Make row-level security actually bite.
 *
 * Three things were wrong at once:
 *
 * 1. Policies cast the tenant GUC straight to uuid. An unset custom GUC reads
 *    as NULL, but one that has been RESET reads as the empty string, and
 *    ''::uuid raises 22P02. So a request that cleared its company context got
 *    an error instead of an empty result. Every policy expression is rewritten
 *    here to NULLIF(..., '') first.
 *
 * 2. A table's owner bypasses its own policies unless the table is FORCEd.
 *    The application role owns the tables in production, so ENABLE without
 *    FORCE meant the policies never ran. Every RLS-enabled table is FORCEd.
 *
 * 3. auth.companies allowed SELECT only to super admins, which under enforced
 *    RLS would make every company invisible to the people who own it. It is
 *    re-scoped to the caller's own memberships.
 */
return new class extends Migration
{
    private const GUCS = ['app.current_company_id', 'app.is_super_admin', 'app.current_user_id', 'app.company_base_currency'];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $this->rescopeCompaniesPolicies();
        $this->rescopeMembershipPolicies();
        $this->guardPolicyExpressions();
        $this->forceRowLevelSecurity();
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Only the FORCE flag is reversed. The null guards are strictly safer
        // than what they replaced and the companies policy fix is a
        // correctness fix; putting either back would reintroduce a fault.
        foreach ($this->forcedTables() as $table) {
            DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
        }
    }

    private function rescopeCompaniesPolicies(): void
    {
        if (DB::selectOne("SELECT to_regclass('auth.companies') AS t")->t === null) {
            return;
        }

        $visible = <<<'SQL'
            (
                COALESCE(NULLIF(current_setting('app.is_super_admin', true), '')::boolean, false)
                OR id = NULLIF(current_setting('app.current_company_id', true), '')::uuid
                OR created_by_user_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                OR EXISTS (
                    SELECT 1 FROM auth.company_user cu
                    WHERE cu.company_id = companies.id
                      AND cu.user_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                )
            )
            SQL;

        DB::statement('DROP POLICY IF EXISTS companies_select_policy ON auth.companies');
        DB::statement("CREATE POLICY companies_select_policy ON auth.companies FOR SELECT USING {$visible}");

        DB::statement('DROP POLICY IF EXISTS companies_update_policy ON auth.companies');
        DB::statement("CREATE POLICY companies_update_policy ON auth.companies FOR UPDATE USING {$visible} WITH CHECK {$visible}");

        if (DB::selectOne("SELECT 1 AS present FROM pg_policy p JOIN pg_class c ON c.oid = p.polrelid JOIN pg_namespace n ON n.oid = c.relnamespace WHERE n.nspname='auth' AND c.relname='companies' AND p.polname='companies_delete_policy'") === null) {
            DB::statement("CREATE POLICY companies_delete_policy ON auth.companies FOR DELETE USING {$visible}");
        }
    }

    /**
     * auth.company_user gated every command on already being an owner or
     * manager of the company, which made the first membership row of a new
     * company impossible to write and made the member list invisible to
     * everyone but the member themselves. Row level security here is tenant
     * isolation; who may manage members inside a tenant is RBAC's job, and is
     * enforced in the application. Scope it the same way every other table is.
     *
     * This policy must not reference auth.companies: the companies policy
     * already reads company_user, and the round trip is infinite recursion.
     */
    private function rescopeMembershipPolicies(): void
    {
        if (DB::selectOne("SELECT to_regclass('auth.company_user') AS t")->t === null) {
            return;
        }

        $scope = <<<'SQL'
            (
                COALESCE(NULLIF(current_setting('app.is_super_admin', true), '')::boolean, false)
                OR company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid
                OR user_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
            )
            SQL;

        foreach (['select' => 'SELECT', 'insert' => 'INSERT', 'update' => 'UPDATE', 'delete' => 'DELETE'] as $suffix => $command) {
            DB::statement("DROP POLICY IF EXISTS company_user_{$suffix}_policy ON auth.company_user");

            $clauses = $command === 'INSERT'
                ? "WITH CHECK {$scope}"
                : ($command === 'UPDATE' ? "USING {$scope} WITH CHECK {$scope}" : "USING {$scope}");

            DB::statement("CREATE POLICY company_user_{$suffix}_policy ON auth.company_user FOR {$command} {$clauses}");
        }
    }

    /**
     * Rewrite every policy expression so a missing or blank GUC yields NULL
     * (no rows) instead of a cast error.
     */
    private function guardPolicyExpressions(): void
    {
        $policies = DB::select(<<<'SQL'
            SELECT n.nspname AS schema_name,
                   c.relname AS table_name,
                   p.polname AS policy_name,
                   pg_get_expr(p.polqual, p.polrelid) AS using_expr,
                   pg_get_expr(p.polwithcheck, p.polrelid) AS check_expr
            FROM pg_policy p
            JOIN pg_class c ON c.oid = p.polrelid
            JOIN pg_namespace n ON n.oid = c.relnamespace
            ORDER BY 1, 2, 3
        SQL);

        foreach ($policies as $policy) {
            $using = $this->guard($policy->using_expr);
            $check = $this->guard($policy->check_expr);

            if ($using === $policy->using_expr && $check === $policy->check_expr) {
                continue;
            }

            $clauses = [];
            if ($using !== null) {
                $clauses[] = "USING ({$using})";
            }
            if ($check !== null) {
                $clauses[] = "WITH CHECK ({$check})";
            }

            if ($clauses === []) {
                continue;
            }

            $table = '"'.$policy->schema_name.'"."'.$policy->table_name.'"';
            DB::statement("ALTER POLICY \"{$policy->policy_name}\" ON {$table} ".implode(' ', $clauses));
        }
    }

    private function guard(?string $expression): ?string
    {
        if ($expression === null || $expression === '') {
            return $expression;
        }

        foreach (self::GUCS as $guc) {
            $quoted = preg_quote($guc, '/');

            // Unwrap any guard that is already there so the wrap below cannot
            // nest, then normalise the two-argument and one-argument forms.
            $expression = preg_replace(
                "/NULLIF\(\s*current_setting\('{$quoted}'(?:::text)?\s*(?:,\s*true)?\s*\)\s*,\s*''(?:::text)?\s*\)/i",
                "current_setting('{$guc}'::text, true)",
                $expression
            );

            $expression = preg_replace(
                "/current_setting\('{$quoted}'(?:::text)?\s*(?:,\s*true)?\s*\)/i",
                "NULLIF(current_setting('{$guc}'::text, true), ''::text)",
                $expression
            );
        }

        return $expression;
    }

    private function forceRowLevelSecurity(): void
    {
        foreach ($this->rlsTables() as $table) {
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
        }
    }

    /** @return list<string> */
    private function rlsTables(): array
    {
        return $this->tables('c.relrowsecurity AND NOT c.relforcerowsecurity');
    }

    /** @return list<string> */
    private function forcedTables(): array
    {
        return $this->tables('c.relforcerowsecurity');
    }

    /** @return list<string> */
    private function tables(string $predicate): array
    {
        $rows = DB::select(<<<SQL
            SELECT format('%I.%I', n.nspname, c.relname) AS ident
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE c.relkind = 'r'
              AND n.nspname NOT IN ('pg_catalog', 'information_schema')
              AND {$predicate}
            ORDER BY 1
        SQL);

        return array_map(static fn ($row) => $row->ident, $rows);
    }
};
