<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'employees',
            'earning_types',
            'deduction_types',
            'benefit_plans',
            'employee_benefits',
            'leave_types',
            'leave_requests',
            'payroll_periods',
            'payslips',
        ];

        foreach ($tables as $table) {
            DB::statement("DROP POLICY IF EXISTS {$table}_company_isolation ON pay.{$table}");
            DB::statement("
                CREATE POLICY {$table}_company_isolation ON pay.{$table}
                FOR ALL
                USING (company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid)
                WITH CHECK (company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid)
            ");
        }

        DB::statement('DROP POLICY IF EXISTS payslip_lines_company_isolation ON pay.payslip_lines');
        DB::statement("
            CREATE POLICY payslip_lines_company_isolation ON pay.payslip_lines
            FOR ALL
            USING (
                payslip_id IN (
                    SELECT id FROM pay.payslips
                    WHERE company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid
                )
            )
            WITH CHECK (
                payslip_id IN (
                    SELECT id FROM pay.payslips
                    WHERE company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid
                )
            )
        ");
    }

    public function down(): void
    {
        $tables = [
            'employees',
            'earning_types',
            'deduction_types',
            'benefit_plans',
            'employee_benefits',
            'leave_types',
            'leave_requests',
            'payroll_periods',
            'payslips',
        ];

        foreach ($tables as $table) {
            DB::statement("DROP POLICY IF EXISTS {$table}_company_isolation ON pay.{$table}");
            DB::statement("
                CREATE POLICY {$table}_company_isolation ON pay.{$table}
                FOR ALL
                USING (company_id = current_setting('app.current_company_id', true)::uuid)
                WITH CHECK (company_id = current_setting('app.current_company_id', true)::uuid)
            ");
        }

        DB::statement('DROP POLICY IF EXISTS payslip_lines_company_isolation ON pay.payslip_lines');
        DB::statement("
            CREATE POLICY payslip_lines_company_isolation ON pay.payslip_lines
            FOR ALL
            USING (
                payslip_id IN (
                    SELECT id FROM pay.payslips
                    WHERE company_id = current_setting('app.current_company_id', true)::uuid
                )
            )
            WITH CHECK (
                payslip_id IN (
                    SELECT id FROM pay.payslips
                    WHERE company_id = current_setting('app.current_company_id', true)::uuid
                )
            )
        ");
    }
};
