<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create all required schemas
        DB::statement('CREATE SCHEMA IF NOT EXISTS auth;');
        DB::statement('CREATE SCHEMA IF NOT EXISTS public;');
        DB::statement('CREATE SCHEMA IF NOT EXISTS hrm;');
        DB::statement('CREATE SCHEMA IF NOT EXISTS acct;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop schemas (cascade drops all objects)
        DB::statement('DROP SCHEMA IF EXISTS auth CASCADE;');
        DB::statement('DROP SCHEMA IF EXISTS hrm CASCADE;');
        DB::statement('DROP SCHEMA IF EXISTS acct CASCADE;');
        // Note: Don't drop public schema as it's the default

        // Recreate empty schemas in case we need to rollback further
        DB::statement('CREATE SCHEMA IF NOT EXISTS auth;');
        DB::statement('CREATE SCHEMA IF NOT EXISTS hrm;');
        DB::statement('CREATE SCHEMA IF NOT EXISTS acct;');
    }
};
