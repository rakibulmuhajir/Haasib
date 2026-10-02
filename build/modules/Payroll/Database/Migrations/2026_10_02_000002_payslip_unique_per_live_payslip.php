<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One payslip per employee per period -- among the live ones. A voided (cancelled) payslip stays on
 * record with its reversal, and the month is run again: Undo approval followed by Run payroll was
 * refused by the old rule, which counted the cancelled payslip too.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE pay.payslips DROP CONSTRAINT IF EXISTS pay_payslips_payroll_period_id_employee_id_unique');
        DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS pay_payslips_period_employee_live_unique
            ON pay.payslips (payroll_period_id, employee_id)
            WHERE status NOT IN ('cancelled', 'voided', 'void')");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS pay.pay_payslips_period_employee_live_unique');
        // Restoring the old rule fails while a re-run month holds a cancelled and a live payslip.
        DB::statement('ALTER TABLE pay.payslips ADD CONSTRAINT pay_payslips_payroll_period_id_employee_id_unique UNIQUE (payroll_period_id, employee_id)');
    }
};
