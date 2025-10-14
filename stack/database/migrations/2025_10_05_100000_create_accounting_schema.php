<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop all tables in acct schema to ensure clean state (drop entire schema and recreate)
        DB::statement('DROP SCHEMA IF EXISTS acct CASCADE');

        // Create acct schema if it doesn't exist
        DB::statement('CREATE SCHEMA IF NOT EXISTS acct');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop all tables in acct schema before dropping schema
        $tables = DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'acct'");

        foreach ($tables as $table) {
            Schema::dropIfExists('acct.'.$table->tablename);
        }

        // Drop the schema
        DB::statement('DROP SCHEMA IF EXISTS acct CASCADE');
    }
};
