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
        // Enable RLS on CRM-related tables
        DB::statement('ALTER TABLE customers ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE vendors ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE contacts ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE interactions ENABLE ROW LEVEL SECURITY');

        // Create RLS policies
        DB::statement("
            CREATE POLICY company_isolation_customers ON customers
            FOR ALL TO PUBLIC
            USING (company_id = current_setting('app.current_company_id', true)::uuid)
            WITH CHECK (company_id = current_setting('app.current_company_id', true)::uuid);
        ");

        DB::statement("
            CREATE POLICY company_isolation_vendors ON vendors
            FOR ALL TO PUBLIC
            USING (company_id = current_setting('app.current_company_id', true)::uuid)
            WITH CHECK (company_id = current_setting('app.current_company_id', true)::uuid);
        ");

        DB::statement("
            CREATE POLICY company_isolation_contacts ON contacts
            FOR ALL TO PUBLIC
            USING (company_id = current_setting('app.current_company_id', true)::uuid)
            WITH CHECK (company_id = current_setting('app.current_company_id', true)::uuid);
        ");

        DB::statement("
            CREATE POLICY company_isolation_interactions ON interactions
            FOR ALL TO PUBLIC
            USING (company_id = current_setting('app.current_company_id', true)::uuid)
            WITH CHECK (company_id = current_setting('app.current_company_id', true)::uuid);
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The down method is intentionally left blank.
        // Dropping policies and disabling RLS can be destructive.
        // If you need to reverse this, it should be done manually with care.
    }
};
