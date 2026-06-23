<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->tables() as $table) {
            DB::statement("DROP POLICY IF EXISTS {$table}_company_isolation ON umrah.{$table}");
            DB::statement("
                CREATE POLICY {$table}_company_isolation ON umrah.{$table}
                FOR ALL
                USING (
                    company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid
                    OR COALESCE(NULLIF(current_setting('app.is_super_admin', true), '')::boolean, false) = true
                )
                WITH CHECK (
                    company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid
                    OR COALESCE(NULLIF(current_setting('app.is_super_admin', true), '')::boolean, false) = true
                )
            ");
        }
    }

    public function down(): void
    {
        foreach ($this->tables() as $table) {
            DB::statement("DROP POLICY IF EXISTS {$table}_company_isolation ON umrah.{$table}");
            DB::statement("
                CREATE POLICY {$table}_company_isolation ON umrah.{$table}
                FOR ALL
                USING (
                    company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid
                    OR COALESCE(NULLIF(current_setting('app.is_super_admin', true), '')::boolean, false) = true
                )
                WITH CHECK (
                    company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid
                    OR COALESCE(NULLIF(current_setting('app.is_super_admin', true), '')::boolean, false) = true
                )
            ");
        }
    }

    private function tables(): array
    {
        return [
            'agents',
            'visa_vendors',
            'vehicle_types',
            'visa_groups',
            'passengers',
            'group_payments',
        ];
    }
};
