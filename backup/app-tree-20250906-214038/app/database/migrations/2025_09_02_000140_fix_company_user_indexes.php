<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Drop redundant composite index (same columns as PK)
        try { DB::statement('drop index if exists auth.company_user_company_id_user_id_index'); } catch (\Throwable $e) { /* ignore */ }
        try { DB::statement('drop index if exists company_user_company_id_user_id_index'); } catch (\Throwable $e) { /* ignore */ }

        // Add standalone index on user_id for fast lookups of a user's companies
        try { DB::statement('create index if not exists company_user_user_id_index on auth.company_user (user_id)'); } catch (\Throwable $e) { /* ignore */ }
    }

    public function down(): void
    {
        // Recreate the composite index only if desired; usually unnecessary because PK covers it
        // Add: drop the user_id index
        try { DB::statement('drop index if exists company_user_user_id_index'); } catch (\Throwable $e) { /* ignore */ }
        // No need to re-add the redundant composite index
    }
};

