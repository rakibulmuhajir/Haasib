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
        // Ensure role used by RLS policies exists
        DB::statement(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'authenticated') THEN
                    CREATE ROLE authenticated NOLOGIN;
                END IF;
            END
            $$;
        SQL);

        // Create accounting schema if it doesn't exist
        DB::statement('CREATE SCHEMA IF NOT EXISTS acct');

        // Set up RLS for accounting schema
        DB::statement('
            CREATE OR REPLACE FUNCTION acct.set_tenant_context(uuid)
            RETURNS void AS $$
            BEGIN
                IF $1 IS NOT NULL THEN
                    PERFORM set_config(\'app.current_company_id\', $1::text, true);
                END IF;
            END;
            $$ LANGUAGE plpgsql SECURITY DEFINER;
        ');

        // Create RLS policy helper function
        DB::statement('
            CREATE OR REPLACE FUNCTION acct.company_id_policy()
            RETURNS text AS $$
            BEGIN
                RETURN NULLIF(current_setting(\'app.current_company_id\', true), \'\')::uuid::text;
            END;
            $$ LANGUAGE plpgsql STABLE;
        ');

        // Add comment to schema
        DB::statement('COMMENT ON SCHEMA acct IS \'Accounting module schema for financial data, journal entries, and charts of accounts\'');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop functions
        DB::statement('DROP FUNCTION IF EXISTS acct.company_id_policy()');
        DB::statement('DROP FUNCTION IF EXISTS acct.set_tenant_context(uuid)');

        // Don\'t drop schema as it might contain other tables
    }
};
