<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Idempotently add unique index on company name to avoid duplicates
        try {
            DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS companies_name_unique ON auth.companies (name)");
        } catch (\Throwable $e) {
            // ignore if driver doesn't support IF NOT EXISTS
        }
    }

    public function down(): void
    {
        try {
            DB::statement("DROP INDEX IF EXISTS companies_name_unique");
        } catch (\Throwable $e) {
            // ignore
        }
    }
};

