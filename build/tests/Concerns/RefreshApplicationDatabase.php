<?php

namespace Tests\Concerns;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;

trait RefreshApplicationDatabase
{
    use RefreshDatabase {
        refreshDatabase as refreshLaravelDatabase;
    }

    public function refreshDatabase(): void
    {
        if (! RefreshDatabaseState::$migrated) {
            $this->assertIsolatedTestingDatabase();
            $this->clearApplicationSchemas();
        }

        $this->refreshLaravelDatabase();
    }

    /**
     * DDL runs as the owning role, which is not the least-privilege role the
     * application connects as when the suite is run with row level security
     * actually enforced.
     */
    protected function migrateDatabases(): void
    {
        $this->artisan('migrate:fresh', $this->migrateFreshUsing() + [
            '--database' => $this->migratorConnection(),
        ]);
    }

    private function migratorConnection(): string
    {
        return config('database.connections.pgsql_migrator') !== null
            ? 'pgsql_migrator'
            : (string) config('database.default');
    }

    private function assertIsolatedTestingDatabase(): void
    {
        $database = (string) DB::connection()->getDatabaseName();

        if (app()->environment() !== 'testing' || ! preg_match('/_(test|testing)$/', $database)) {
            throw new \RuntimeException("Refusing to reset non-testing database [{$database}].");
        }
    }

    private function clearApplicationSchemas(): void
    {
        $connection = DB::connection($this->migratorConnection());

        foreach (['umrah', 'fuel', 'pay', 'inv', 'crm', 'hsp', 'audit', 'acct', 'auth'] as $schema) {
            $connection->statement("DROP SCHEMA IF EXISTS {$schema} CASCADE");
        }
    }
}
