<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Create the least-privilege role the application connects as.
 *
 * Why a migration and not an artisan command: the grants have to track the
 * schema, and the schema is versioned here. A command would be a second,
 * hand-run source of truth that drifts the moment someone adds a table. The
 * migration runs in the same ordered stream as the DDL it is granting over,
 * and the default privileges below cover everything created afterwards.
 *
 * The role is deliberately NOT given ownership of anything, is NOSUPERUSER and
 * NOBYPASSRLS, so row-level security is actually evaluated against it. Where
 * the role does end up owning tables (it owns them in production, and it owns
 * them when it runs the test suite's own migrations), FORCE ROW LEVEL SECURITY
 * -- applied by the enforce_row_level_security migration -- closes the owner
 * bypass.
 */
return new class extends Migration
{
    private const ROLE = 'haasib_app';

    private const SCHEMAS = ['public', 'auth', 'acct', 'inv', 'tax', 'pay', 'fuel', 'hsp', 'crm', 'audit', 'umrah'];

    public function shouldRun(): bool
    {
        // Skipping through the migrator leaves this migration pending. Returning
        // only from up() would incorrectly record it as applied.
        return strtolower((string) env('RLS_ENFORCEMENT', 'off')) === 'on';
    }

    public function up(): void
    {
        // Keep direct invocations gated as well as normal artisan migrations.
        if (! $this->shouldRun()) {
            return;
        }

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $role = self::ROLE;

        if (! $this->roleExists($role)) {
            if (! $this->canCreateRoles()) {
                // Already connected as a restricted role (the test suite does
                // this): nothing to provision and no privilege to do it with.
                return;
            }

            $password = (string) env('DB_APP_ROLE_PASSWORD', '');
            $clause = $password === ''
                ? ''
                : ' PASSWORD '.$this->quoteLiteral($password);

            DB::statement("CREATE ROLE \"{$role}\" LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS INHERIT{$clause}");
        }

        if (! $this->canCreateRoles()) {
            return;
        }

        DB::statement("ALTER ROLE \"{$role}\" NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS");

        $database = DB::connection()->getDatabaseName();
        DB::statement('GRANT CONNECT ON DATABASE '.$this->quoteIdentifier($database).' TO "'.$role.'"');

        foreach (self::SCHEMAS as $schema) {
            if (! $this->schemaExists($schema)) {
                continue;
            }

            DB::statement("GRANT USAGE ON SCHEMA \"{$schema}\" TO \"{$role}\"");
            DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA \"{$schema}\" TO \"{$role}\"");
            DB::statement("GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA \"{$schema}\" TO \"{$role}\"");
            DB::statement("GRANT EXECUTE ON ALL FUNCTIONS IN SCHEMA \"{$schema}\" TO \"{$role}\"");

            // Anything created later by whoever runs migrations.
            DB::statement("ALTER DEFAULT PRIVILEGES IN SCHEMA \"{$schema}\" GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO \"{$role}\"");
            DB::statement("ALTER DEFAULT PRIVILEGES IN SCHEMA \"{$schema}\" GRANT USAGE, SELECT ON SEQUENCES TO \"{$role}\"");
            DB::statement("ALTER DEFAULT PRIVILEGES IN SCHEMA \"{$schema}\" GRANT EXECUTE ON FUNCTIONS TO \"{$role}\"");
        }
    }

    public function down(): void
    {
        // Dropping a login role that the running application may be connected
        // as is not something a rollback should do unattended. Revoking is the
        // reversible half; the role itself is left in place.
        if (DB::connection()->getDriverName() !== 'pgsql' || ! $this->roleExists(self::ROLE) || ! $this->canCreateRoles()) {
            return;
        }

        $role = self::ROLE;

        foreach (self::SCHEMAS as $schema) {
            if (! $this->schemaExists($schema)) {
                continue;
            }

            DB::statement("ALTER DEFAULT PRIVILEGES IN SCHEMA \"{$schema}\" REVOKE SELECT, INSERT, UPDATE, DELETE ON TABLES FROM \"{$role}\"");
            DB::statement("ALTER DEFAULT PRIVILEGES IN SCHEMA \"{$schema}\" REVOKE USAGE, SELECT ON SEQUENCES FROM \"{$role}\"");
            DB::statement("ALTER DEFAULT PRIVILEGES IN SCHEMA \"{$schema}\" REVOKE EXECUTE ON FUNCTIONS FROM \"{$role}\"");
            DB::statement("REVOKE ALL ON ALL TABLES IN SCHEMA \"{$schema}\" FROM \"{$role}\"");
            DB::statement("REVOKE ALL ON ALL SEQUENCES IN SCHEMA \"{$schema}\" FROM \"{$role}\"");
            DB::statement("REVOKE ALL ON SCHEMA \"{$schema}\" FROM \"{$role}\"");
        }
    }

    private function roleExists(string $role): bool
    {
        return DB::selectOne('SELECT 1 AS present FROM pg_roles WHERE rolname = ?', [$role]) !== null;
    }

    private function canCreateRoles(): bool
    {
        $row = DB::selectOne('SELECT rolsuper, rolcreaterole FROM pg_roles WHERE rolname = current_user');

        return $row !== null && ((bool) $row->rolsuper || (bool) $row->rolcreaterole);
    }

    private function schemaExists(string $schema): bool
    {
        return DB::selectOne('SELECT 1 AS present FROM pg_namespace WHERE nspname = ?', [$schema]) !== null;
    }

    private function quoteIdentifier(string $value): string
    {
        return '"'.str_replace('"', '""', $value).'"';
    }

    private function quoteLiteral(string $value): string
    {
        return "'".str_replace("'", "''", $value)."'";
    }
};
