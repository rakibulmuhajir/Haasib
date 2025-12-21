<?php

namespace App\Modules\FuelStation\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class FuelStationModuleInstaller
{
    /**
     * Ensure FuelStation module migrations are applied.
     *
     * Note: These migrations are global (schema/table creation), not per-company.
     */
    public function ensureMigrationsApplied(): void
    {
        if ($this->isInstalled()) {
            return;
        }

        $this->withInstallLock(function (): void {
            if ($this->isInstalled()) {
                return;
            }

            Artisan::call('migrate', [
                '--path' => 'modules/FuelStation/Database/Migrations',
                '--force' => true,
                '--no-interaction' => true,
            ]);
        });
    }

    private function isInstalled(): bool
    {
        $row = DB::selectOne("
            SELECT EXISTS (
                SELECT 1
                FROM information_schema.tables
                WHERE table_schema = 'fuel'
                  AND table_name = 'pumps'
            ) AS exists
        ");

        return (bool) ($row?->exists ?? false);
    }

    private function withInstallLock(callable $callback): void
    {
        // Prevent concurrent attempts to run the same module migrations.
        DB::select("SELECT pg_advisory_lock(hashtext('haasib:module_migrate:fuel_station'))");

        try {
            $callback();
        } finally {
            DB::select("SELECT pg_advisory_unlock(hashtext('haasib:module_migrate:fuel_station'))");
        }
    }
}
