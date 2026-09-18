<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A company row and its bootstrap (chart of accounts, fiscal year, posting templates,
     * bank account) are created in two separate transactions -- see
     * app/Http/Controllers/CompanyController@store and App\Services\CompanyBootstrapService.
     * If bootstrap fails partway (e.g. IndustryCoaPackNotSeededException, an industry pack
     * with zero templates), the company row survives with no chart of accounts at all.
     * This column marks that state explicitly so the company can be blocked from ordinary
     * use until repaired, instead of silently behaving as though it were ready.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE auth.companies ADD COLUMN IF NOT EXISTS bootstrap_incomplete_at TIMESTAMP NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE auth.companies DROP COLUMN IF EXISTS bootstrap_incomplete_at');
    }
};
