<?php

namespace App\Support\Database;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Log;

/**
 * Make a missing tenant context loud, in development and test only.
 *
 * Enforced row level security fails asymmetrically. A write with no
 * app.current_company_id is refused with SQLSTATE 42501 -- loud, and easy to
 * find. A *read* with no context matches nothing and reports success, which
 * looks exactly like a genuinely empty table. That is how an incomplete
 * rollout produces screens that are silently blank for some tenants.
 *
 * This guard closes that gap by watching the query stream. When a query
 * touches a company-scoped table (row level security enabled, with a policy
 * that reads app.current_company_id) while the session carries no company
 * context and is not in the policies' super-admin escape hatch, it raises --
 * so the violation surfaces as a test failure at a desk rather than as an
 * empty report in production.
 *
 * It is OFF unless database.tenant_context_guard says otherwise, and is
 * refused outright in production.
 *
 * Modes: 'off' (default), 'log' (warning to the log), 'throw'.
 *
 * What it cannot see:
 *  - Raw PDO used outside Laravel's connection, which emits no QueryExecuted.
 *  - Whether the context that IS set is the *right* company. It answers
 *    "is there a tenant context", not "is it the correct one".
 *  - Queries on connections other than the guarded ones. The schema-named
 *    connections and pgsql_migrator are separate sessions and are not guarded.
 *  - Anything that never reaches the database: a cached response, a query
 *    short-circuited in PHP.
 */
class TenantContextGuard
{
    public const MODE_OFF = 'off';

    public const MODE_LOG = 'log';

    public const MODE_THROW = 'throw';

    /** @var array<string, string|null> Mirror of app.current_company_id, per connection. */
    private array $companyId = [];

    /** @var array<string, bool> Mirror of app.is_super_admin, per connection. */
    private array $superAdmin = [];

    /** @var array<string, string>|null Lookup key => schema-qualified company-scoped table. */
    private ?array $tenantTables = null;

    /** @var array<string, list<string>> Memoised table extraction, keyed by SQL. */
    private array $tableCache = [];

    private bool $inspecting = false;

    /**
     * @param  list<string>  $connections
     */
    public function __construct(
        private readonly string $mode = self::MODE_OFF,
        private readonly array $connections = [],
    ) {}

    public function enabled(): bool
    {
        return $this->mode !== self::MODE_OFF;
    }

    public function handle(QueryExecuted $event): void
    {
        if (! $this->enabled() || $this->inspecting) {
            return;
        }

        $sql = $event->sql;

        // Track the session settings we care about without a round trip.
        if ($this->trackContextChange($event)) {
            return;
        }

        if (! in_array($event->connectionName, $this->connections, true)) {
            return;
        }

        // Schema changes move the goalposts: recompute which tables are scoped.
        if (preg_match('/^\s*(create|drop|alter|truncate|comment)\b/i', $sql)) {
            $this->flush();

            return;
        }

        if (! preg_match('/^\s*(select|insert|update|delete|with)\b/i', $sql)) {
            return;
        }

        if (($this->companyId[$event->connectionName] ?? null) !== null) {
            return;
        }

        if (($this->superAdmin[$event->connectionName] ?? false) === true) {
            return;
        }

        $touched = $this->tenantTablesIn($event->connection, $sql);

        if ($touched === []) {
            return;
        }

        // The mirror only suspects. Confirm against the session itself before
        // accusing anyone: context set by a path the guard never saw is real
        // context.
        if (! $this->confirmMissing($event)) {
            return;
        }

        $exception = MissingCompanyContextException::forQuery($touched[0], $sql, $event->connectionName);

        if ($this->mode === self::MODE_THROW) {
            throw $exception;
        }

        Log::warning($exception->getMessage());
    }

    /**
     * @return bool True when the query was itself a context change.
     */
    private function trackContextChange(QueryExecuted $event): bool
    {
        $sql = $event->sql;

        if (stripos($sql, 'set_config') !== false) {
            foreach ([
                'app.current_company_id' => 'companyId',
                'app.is_super_admin' => 'superAdmin',
            ] as $guc => $property) {
                $pattern = "/set_config\(\s*'".preg_quote($guc, '/')."'\s*,\s*([^,]+),/i";

                if (! preg_match($pattern, $sql, $matches)) {
                    continue;
                }

                $raw = trim($matches[1]);
                $value = $raw === '?'
                    ? (string) ($event->bindings[0] ?? '')
                    : trim($raw, "' ");

                $this->store($event->connectionName, $property, $value);
            }

            return true;
        }

        if (preg_match('/^\s*reset\s+(app\.current_company_id|app\.is_super_admin)/i', $sql, $matches)) {
            $this->store(
                $event->connectionName,
                strtolower($matches[1]) === 'app.current_company_id' ? 'companyId' : 'superAdmin',
                ''
            );

            return true;
        }

        return false;
    }

    private function store(string $connection, string $property, string $value): void
    {
        if ($property === 'companyId') {
            $this->companyId[$connection] = $value === '' ? null : $value;

            return;
        }

        $this->superAdmin[$connection] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function confirmMissing(QueryExecuted $event): bool
    {
        $this->inspecting = true;

        try {
            $row = $event->connection->selectOne(
                "SELECT NULLIF(current_setting('app.current_company_id', true), '') AS company_id, "
                ."COALESCE(NULLIF(current_setting('app.is_super_admin', true), '')::boolean, false) AS super_admin"
            );
        } catch (\Throwable) {
            // An aborted transaction cannot answer. Believe the mirror.
            return true;
        } finally {
            $this->inspecting = false;
        }

        $this->companyId[$event->connectionName] = ($row?->company_id ?: null);
        $this->superAdmin[$event->connectionName] = (bool) ($row?->super_admin ?? false);

        return $this->companyId[$event->connectionName] === null
            && $this->superAdmin[$event->connectionName] === false;
    }

    /**
     * @return list<string> The company-scoped tables this SQL touches.
     */
    private function tenantTablesIn(\Illuminate\Database\Connection $connection, string $sql): array
    {
        if (array_key_exists($sql, $this->tableCache)) {
            return $this->tableCache[$sql];
        }

        $scoped = $this->tenantTables($connection);

        if ($scoped === []) {
            return [];
        }

        $found = [];

        preg_match_all(
            '/\b(?:from|join|into|update)\s+((?:"[^"]+"|[A-Za-z_][A-Za-z0-9_$]*)(?:\s*\.\s*(?:"[^"]+"|[A-Za-z_][A-Za-z0-9_$]*))?)/i',
            $sql,
            $matches
        );

        foreach ($matches[1] ?? [] as $reference) {
            $parts = array_map(
                static fn (string $part) => trim(trim($part), '"'),
                explode('.', $reference)
            );

            $key = count($parts) > 1
                ? strtolower($parts[0].'.'.$parts[1])
                : strtolower($parts[0]);

            if (isset($scoped[$key])) {
                $found[$scoped[$key]] = true;
            }
        }

        return $this->tableCache[$sql] = array_keys($found);
    }

    /**
     * Company-scoped means: row level security is on, and at least one policy
     * reads app.current_company_id. Those are exactly the tables where a
     * missing context silently changes the answer.
     *
     * @return array<string, string> lookup key => schema-qualified name
     */
    private function tenantTables(\Illuminate\Database\Connection $connection): array
    {
        if ($this->tenantTables !== null) {
            return $this->tenantTables;
        }

        if ($connection->getDriverName() !== 'pgsql') {
            return $this->tenantTables = [];
        }

        $this->inspecting = true;

        try {
            $rows = $connection->select(
                "SELECT n.nspname AS schema_name, c.relname AS table_name\n"
                ."FROM pg_class c\n"
                ."JOIN pg_namespace n ON n.oid = c.relnamespace\n"
                ."WHERE c.relkind = 'r'\n"
                ."  AND c.relrowsecurity\n"
                ."  AND EXISTS (\n"
                ."      SELECT 1 FROM pg_policy p\n"
                ."      WHERE p.polrelid = c.oid\n"
                ."        AND (COALESCE(pg_get_expr(p.polqual, p.polrelid), '') LIKE '%app.current_company_id%'\n"
                ."          OR COALESCE(pg_get_expr(p.polwithcheck, p.polrelid), '') LIKE '%app.current_company_id%')\n"
                .'  )'
            );
        } catch (\Throwable) {
            // Mid-migration, or a transaction already aborted. Stay inert and
            // try again on the next query rather than caching an empty survey.
            return [];
        } finally {
            $this->inspecting = false;
        }

        $lookup = [];

        foreach ($rows as $row) {
            $qualified = $row->schema_name.'.'.$row->table_name;
            $lookup[strtolower($qualified)] = $qualified;
            // An unqualified reference resolves through search_path; treat a
            // bare name as scoped if any schema has a scoped table by it.
            $lookup[strtolower($row->table_name)] ??= $qualified;
        }

        $this->tableCache = [];

        return $this->tenantTables = $lookup;
    }

    /** Forget the cached schema survey. Migrations invalidate it. */
    public function flush(): void
    {
        $this->tenantTables = null;
        $this->tableCache = [];
    }
}
