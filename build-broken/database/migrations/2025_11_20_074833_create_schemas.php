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
        // Create schemas for multi-tenant architecture
        DB::statement('CREATE SCHEMA IF NOT EXISTS auth');
        DB::statement('CREATE SCHEMA IF NOT EXISTS acct');
        
        // Enable UUID extension if not already enabled
        DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp"');
        
        // Set proper search path for future migrations
        DB::statement('ALTER DATABASE ' . DB::getDatabaseName() . ' SET search_path TO public, auth, acct');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: Be careful dropping schemas in production
        // This is primarily for development/testing
        
        DB::statement('DROP SCHEMA IF EXISTS acct CASCADE');
        DB::statement('DROP SCHEMA IF EXISTS auth CASCADE');
    }
};
