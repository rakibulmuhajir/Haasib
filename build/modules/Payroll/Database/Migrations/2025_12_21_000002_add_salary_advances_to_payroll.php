<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add salary advances feature to payroll module.
 * Allows tracking of salary advances given to employees and their recovery through payslips.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─────────────────────────────────────────────────────────────────────
        // pay.salary_advances - Track advances given to employees
        // ─────────────────────────────────────────────────────────────────────
        Schema::create('pay.salary_advances', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('employee_id');
            $table->date('advance_date');
            $table->decimal('amount', 15, 2);
            $table->decimal('amount_recovered', 15, 2)->default(0);
            $table->decimal('amount_outstanding', 15, 2); // Trigger will keep this updated

            $table->string('reason', 255)->nullable();
            $table->string('status', 20)->default('pending'); // pending, partially_recovered, fully_recovered, cancelled

            // Payment method
            $table->string('payment_method', 30)->default('cash'); // cash, bank_transfer, cheque
            $table->uuid('bank_account_id')->nullable(); // Which bank account used
            $table->string('reference', 100)->nullable(); // Cheque number, transfer ref, etc.

            // GL integration
            $table->uuid('journal_entry_id')->nullable(); // JE when advance is given
            $table->uuid('advance_account_id')->nullable(); // Asset account (Employee Advances Receivable)

            $table->uuid('approved_by_user_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->uuid('recorded_by_user_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('company_id')
                ->references('id')->on('auth.companies')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('employee_id')
                ->references('id')->on('pay.employees')
                ->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('bank_account_id')
                ->references('id')->on('acct.accounts')
                ->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('journal_entry_id')
                ->references('id')->on('acct.journal_entries')
                ->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('advance_account_id')
                ->references('id')->on('acct.accounts')
                ->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('approved_by_user_id')
                ->references('id')->on('auth.users')
                ->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('recorded_by_user_id')
                ->references('id')->on('auth.users')
                ->nullOnDelete()->cascadeOnUpdate();

            // Indexes
            $table->index('company_id');
            $table->index('employee_id');
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'advance_date']);
            $table->index(['employee_id', 'status']);
        });

        // Check constraint for status enum
        DB::statement("ALTER TABLE pay.salary_advances ADD CONSTRAINT salary_advances_status_check
            CHECK (status IN ('pending', 'partially_recovered', 'fully_recovered', 'cancelled'))");

        // Check constraint for payment_method enum
        DB::statement("ALTER TABLE pay.salary_advances ADD CONSTRAINT salary_advances_payment_method_check
            CHECK (payment_method IN ('cash', 'bank_transfer', 'cheque'))");

        // ─────────────────────────────────────────────────────────────────────
        // pay.salary_advance_recoveries - Track individual recovery transactions
        // ─────────────────────────────────────────────────────────────────────
        Schema::create('pay.salary_advance_recoveries', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('salary_advance_id');
            $table->uuid('payslip_id')->nullable(); // If recovered through payroll
            $table->date('recovery_date');
            $table->decimal('amount', 15, 2);
            $table->string('recovery_type', 20); // payroll_deduction, manual_repayment, adjustment

            $table->string('reference', 100)->nullable();
            $table->uuid('journal_entry_id')->nullable();
            $table->uuid('recorded_by_user_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('company_id')
                ->references('id')->on('auth.companies')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('salary_advance_id')
                ->references('id')->on('pay.salary_advances')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('payslip_id')
                ->references('id')->on('pay.payslips')
                ->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('journal_entry_id')
                ->references('id')->on('acct.journal_entries')
                ->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('recorded_by_user_id')
                ->references('id')->on('auth.users')
                ->nullOnDelete()->cascadeOnUpdate();

            // Indexes
            $table->index('company_id');
            $table->index('salary_advance_id');
            $table->index('payslip_id');
            $table->index(['company_id', 'recovery_date']);
        });

        // Check constraint for recovery_type enum
        DB::statement("ALTER TABLE pay.salary_advance_recoveries ADD CONSTRAINT salary_advance_recoveries_type_check
            CHECK (recovery_type IN ('payroll_deduction', 'manual_repayment', 'adjustment'))");

        // ─────────────────────────────────────────────────────────────────────
        // RLS Policies
        // ─────────────────────────────────────────────────────────────────────
        $tables = ['salary_advances', 'salary_advance_recoveries'];

        foreach ($tables as $tableName) {
            DB::statement("ALTER TABLE pay.{$tableName} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE pay.{$tableName} FORCE ROW LEVEL SECURITY");

            // Super-admin bypass policy
            DB::statement("CREATE POLICY {$tableName}_super_admin ON pay.{$tableName}
                FOR ALL
                USING (
                    current_setting('app.current_user_id', true) IS NOT NULL
                    AND current_setting('app.current_user_id', true)::text LIKE '00000000-0000-0000-0000-%'
                )
                WITH CHECK (
                    current_setting('app.current_user_id', true) IS NOT NULL
                    AND current_setting('app.current_user_id', true)::text LIKE '00000000-0000-0000-0000-%'
                )
            ");

            // Company isolation policy
            DB::statement("CREATE POLICY {$tableName}_company_isolation ON pay.{$tableName}
                FOR ALL
                USING (company_id = current_setting('app.current_company_id', true)::uuid)
                WITH CHECK (company_id = current_setting('app.current_company_id', true)::uuid)
            ");
        }

        // ─────────────────────────────────────────────────────────────────────
        // Trigger: Update salary advance totals and status when recoveries change
        // ─────────────────────────────────────────────────────────────────────
        DB::statement("
            CREATE OR REPLACE FUNCTION pay.update_salary_advance_on_recovery()
            RETURNS TRIGGER AS \$\$
            DECLARE
                v_advance_id uuid;
                v_total_recovered numeric(15,2);
                v_amount numeric(15,2);
                v_new_status text;
            BEGIN
                v_advance_id := COALESCE(NEW.salary_advance_id, OLD.salary_advance_id);

                -- Calculate total recovered
                SELECT COALESCE(SUM(amount), 0)
                INTO v_total_recovered
                FROM pay.salary_advance_recoveries
                WHERE salary_advance_id = v_advance_id;

                -- Get advance amount
                SELECT amount INTO v_amount
                FROM pay.salary_advances
                WHERE id = v_advance_id;

                -- Determine status
                IF v_total_recovered >= v_amount THEN
                    v_new_status := 'fully_recovered';
                ELSIF v_total_recovered > 0 THEN
                    v_new_status := 'partially_recovered';
                ELSE
                    v_new_status := 'pending';
                END IF;

                -- Update the advance
                UPDATE pay.salary_advances
                SET
                    amount_recovered = v_total_recovered,
                    amount_outstanding = GREATEST(0, v_amount - v_total_recovered),
                    status = v_new_status,
                    updated_at = NOW()
                WHERE id = v_advance_id;

                RETURN COALESCE(NEW, OLD);
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        DB::statement("
            CREATE TRIGGER trg_salary_advance_recoveries_update
            AFTER INSERT OR UPDATE OR DELETE ON pay.salary_advance_recoveries
            FOR EACH ROW
            EXECUTE FUNCTION pay.update_salary_advance_on_recovery();
        ");

        // ─────────────────────────────────────────────────────────────────────
        // Add deduction type for salary advance recovery
        // ─────────────────────────────────────────────────────────────────────
        // This will be seeded via a seeder, but we ensure the column exists
        // for linking advance deductions on payslip lines
        Schema::table('pay.payslip_lines', function (Blueprint $table) {
            $table->uuid('salary_advance_id')->nullable()->after('deduction_type_id');

            $table->foreign('salary_advance_id')
                ->references('id')->on('pay.salary_advances')
                ->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        // Drop trigger and function
        DB::statement('DROP TRIGGER IF EXISTS trg_salary_advance_recoveries_update ON pay.salary_advance_recoveries');
        DB::statement('DROP FUNCTION IF EXISTS pay.update_salary_advance_on_recovery()');

        // Drop FK from payslip_lines
        Schema::table('pay.payslip_lines', function (Blueprint $table) {
            $table->dropForeign(['salary_advance_id']);
            $table->dropColumn('salary_advance_id');
        });

        // Drop tables in reverse order
        Schema::dropIfExists('pay.salary_advance_recoveries');
        Schema::dropIfExists('pay.salary_advances');
    }
};
