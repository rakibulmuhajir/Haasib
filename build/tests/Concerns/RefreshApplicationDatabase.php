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

    private function assertIsolatedTestingDatabase(): void
    {
        $database = (string) DB::connection()->getDatabaseName();

        if (app()->environment() !== 'testing' || ! preg_match('/_(test|testing)$/', $database)) {
            throw new \RuntimeException("Refusing to reset non-testing database [{$database}].");
        }
    }

    private function clearApplicationSchemas(): void
    {
        foreach (['umrah', 'fuel', 'pay', 'inv', 'crm', 'hsp', 'audit', 'acct', 'auth'] as $schema) {
            DB::statement("DROP SCHEMA IF EXISTS {$schema} CASCADE");
        }
    }
}
