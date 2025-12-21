<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create 'pay' schema (and keep fresh installs reliable in multi-schema PostgreSQL).
        // Laravel's migrate:fresh can leave behind tables in non-default schemas; if that happens,
        // we need to drop the payroll schema with CASCADE before recreating it.
        $hasPayTables = (bool) (DB::selectOne("
            SELECT EXISTS (
                SELECT 1
                FROM information_schema.tables
                WHERE table_schema = 'pay'
            ) AS exists
        ")?->exists ?? false);

        if ($hasPayTables) {
            DB::statement('DROP SCHEMA IF EXISTS pay CASCADE');
        }

        DB::statement('CREATE SCHEMA IF NOT EXISTS pay');

        // 1. pay.employees
        Schema::create('pay.employees', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('user_id')->nullable();
            $table->string('employee_number', 50);
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('national_id', 100)->nullable();
            $table->string('tax_id', 100)->nullable();
            $table->jsonb('address')->nullable();
            $table->date('hire_date');
            $table->date('termination_date')->nullable();
            $table->string('termination_reason', 255)->nullable();
            $table->string('employment_type', 30)->default('full_time');
            $table->string('employment_status', 30)->default('active');
            $table->string('department', 100)->nullable();
            $table->string('position', 100)->nullable();
            $table->uuid('manager_id')->nullable();
            $table->string('pay_frequency', 20)->default('monthly');
            $table->decimal('base_salary', 15, 2)->default(0);
            $table->decimal('hourly_rate', 10, 4)->nullable();
            $table->char('currency', 3);
            $table->string('bank_account_name', 255)->nullable();
            $table->string('bank_account_number', 100)->nullable();
            $table->string('bank_name', 255)->nullable();
            $table->string('bank_routing_number', 50)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('auth.users')->nullOnDelete();
            $table->foreign('currency')->references('code')->on('public.currencies');
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->nullOnDelete();
            $table->foreign('updated_by_user_id')->references('id')->on('auth.users')->nullOnDelete();

            $table->index('company_id');
            $table->index(['company_id', 'employment_status']);
            $table->index('manager_id');
            $table->index('department');
        });

        // Self-referencing FK for manager_id (must be added after table creation)
        Schema::table('pay.employees', function (Blueprint $table) {
            $table->foreign('manager_id')->references('id')->on('pay.employees')->nullOnDelete();
        });

        // Unique constraint for employee_number per company (soft delete aware)
        DB::statement('CREATE UNIQUE INDEX employees_company_employee_number_unique ON pay.employees (company_id, employee_number) WHERE deleted_at IS NULL');

        // 2. pay.earning_types
        Schema::create('pay.earning_types', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->string('code', 50);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->boolean('is_taxable')->default(true);
            $table->boolean('affects_overtime')->default(false);
            $table->boolean('is_recurring')->default(true);
            $table->uuid('gl_account_id')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete();

            $table->index('company_id');
            $table->index(['company_id', 'is_active']);
        });

        DB::statement('CREATE UNIQUE INDEX earning_types_company_code_unique ON pay.earning_types (company_id, code) WHERE deleted_at IS NULL');

        // 3. pay.deduction_types
        Schema::create('pay.deduction_types', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->string('code', 50);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->boolean('is_pre_tax')->default(false);
            $table->boolean('is_statutory')->default(false);
            $table->boolean('is_recurring')->default(true);
            $table->uuid('gl_account_id')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete();

            $table->index('company_id');
            $table->index(['company_id', 'is_active']);
        });

        DB::statement('CREATE UNIQUE INDEX deduction_types_company_code_unique ON pay.deduction_types (company_id, code) WHERE deleted_at IS NULL');

        // 4. pay.benefit_plans
        Schema::create('pay.benefit_plans', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->string('code', 50);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('benefit_type', 30);
            $table->string('provider', 255)->nullable();
            $table->decimal('employee_contrib_rate', 7, 4)->default(0);
            $table->decimal('employer_contrib_rate', 7, 4)->default(0);
            $table->decimal('employee_fixed_amount', 15, 2)->nullable();
            $table->decimal('employer_fixed_amount', 15, 2)->nullable();
            $table->char('currency', 3);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete();
            $table->foreign('currency')->references('code')->on('public.currencies');

            $table->unique(['company_id', 'code']);
            $table->index('company_id');
            $table->index(['company_id', 'is_active']);
        });

        // 5. pay.employee_benefits
        Schema::create('pay.employee_benefits', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('employee_id');
            $table->uuid('benefit_plan_id');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('employee_override_amount', 15, 2)->nullable();
            $table->decimal('employer_override_amount', 15, 2)->nullable();
            $table->string('coverage_level', 30)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete();
            $table->foreign('employee_id')->references('id')->on('pay.employees')->cascadeOnDelete();
            $table->foreign('benefit_plan_id')->references('id')->on('pay.benefit_plans')->cascadeOnDelete();

            $table->unique(['employee_id', 'benefit_plan_id', 'start_date']);
            $table->index('company_id');
            $table->index('employee_id');
            $table->index('benefit_plan_id');
        });

        // 6. pay.leave_types
        Schema::create('pay.leave_types', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->string('code', 50);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->boolean('is_paid')->default(true);
            $table->decimal('accrual_rate_hours', 7, 3)->default(0);
            $table->decimal('max_carryover_hours', 7, 3)->nullable();
            $table->decimal('max_balance_hours', 7, 3)->nullable();
            $table->boolean('requires_approval')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete();

            $table->unique(['company_id', 'code']);
            $table->index('company_id');
            $table->index(['company_id', 'is_active']);
        });

        // 7. pay.leave_requests
        Schema::create('pay.leave_requests', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('employee_id');
            $table->uuid('leave_type_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('hours', 7, 2);
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('pending');
            $table->uuid('approved_by_user_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('rejection_reason', 255)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete();
            $table->foreign('employee_id')->references('id')->on('pay.employees')->cascadeOnDelete();
            $table->foreign('leave_type_id')->references('id')->on('pay.leave_types')->restrictOnDelete();
            $table->foreign('approved_by_user_id')->references('id')->on('auth.users')->nullOnDelete();

            $table->index('company_id');
            $table->index('employee_id');
            $table->index(['company_id', 'status']);
            $table->index(['employee_id', 'start_date', 'end_date']);
        });

        // Check constraint: end_date >= start_date
        DB::statement('ALTER TABLE pay.leave_requests ADD CONSTRAINT leave_requests_date_check CHECK (end_date >= start_date)');

        // 8. pay.payroll_periods
        Schema::create('pay.payroll_periods', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->date('period_start');
            $table->date('period_end');
            $table->date('payment_date');
            $table->string('status', 20)->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->uuid('closed_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete();
            $table->foreign('closed_by_user_id')->references('id')->on('auth.users')->nullOnDelete();

            $table->unique(['company_id', 'period_start', 'period_end']);
            $table->index('company_id');
            $table->index(['company_id', 'status']);
        });

        // Check constraint: period_end > period_start
        DB::statement('ALTER TABLE pay.payroll_periods ADD CONSTRAINT payroll_periods_date_check CHECK (period_end > period_start)');

        // 9. pay.payslips
        Schema::create('pay.payslips', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('payroll_period_id');
            $table->uuid('employee_id');
            $table->string('payslip_number', 50);
            $table->char('currency', 3);
            $table->decimal('gross_pay', 15, 2)->default(0);
            $table->decimal('total_earnings', 15, 2)->default(0);
            $table->decimal('total_deductions', 15, 2)->default(0);
            $table->decimal('employer_costs', 15, 2)->default(0);
            $table->decimal('net_pay', 15, 2)->default(0);
            $table->string('status', 20)->default('draft');
            $table->timestamp('approved_at')->nullable();
            $table->uuid('approved_by_user_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method', 30)->nullable();
            $table->string('payment_reference', 100)->nullable();
            $table->uuid('gl_transaction_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete();
            $table->foreign('payroll_period_id')->references('id')->on('pay.payroll_periods')->cascadeOnDelete();
            $table->foreign('employee_id')->references('id')->on('pay.employees')->restrictOnDelete();
            $table->foreign('currency')->references('code')->on('public.currencies');
            $table->foreign('approved_by_user_id')->references('id')->on('auth.users')->nullOnDelete();

            $table->unique(['company_id', 'payslip_number']);
            $table->unique(['payroll_period_id', 'employee_id']);
            $table->index('company_id');
            $table->index('payroll_period_id');
            $table->index('employee_id');
            $table->index(['company_id', 'status']);
        });

        // 10. pay.payslip_lines
        Schema::create('pay.payslip_lines', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('payslip_id');
            $table->string('line_type', 20);
            $table->uuid('earning_type_id')->nullable();
            $table->uuid('deduction_type_id')->nullable();
            $table->string('description', 255)->nullable();
            $table->decimal('quantity', 10, 3)->default(1);
            $table->decimal('rate', 15, 4)->default(0);
            $table->decimal('amount', 15, 2);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('payslip_id')->references('id')->on('pay.payslips')->cascadeOnDelete();
            $table->foreign('earning_type_id')->references('id')->on('pay.earning_types')->nullOnDelete();
            $table->foreign('deduction_type_id')->references('id')->on('pay.deduction_types')->nullOnDelete();

            $table->index('payslip_id');
        });

        // Check constraint: amount >= 0
        DB::statement('ALTER TABLE pay.payslip_lines ADD CONSTRAINT payslip_lines_amount_check CHECK (amount >= 0)');

        // Check constraint: line_type validity
        DB::statement("ALTER TABLE pay.payslip_lines ADD CONSTRAINT payslip_lines_type_check CHECK (
            (line_type = 'earning' AND earning_type_id IS NOT NULL) OR
            (line_type = 'deduction' AND deduction_type_id IS NOT NULL) OR
            (line_type = 'employer')
        )");

        // ===== RLS Policies =====
        $rlsTables = [
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

        foreach ($rlsTables as $tableName) {
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

        // payslip_lines inherits from payslips (no company_id column)
        DB::statement('ALTER TABLE pay.payslip_lines ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE pay.payslip_lines FORCE ROW LEVEL SECURITY');

        DB::statement("CREATE POLICY payslip_lines_super_admin ON pay.payslip_lines
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

        DB::statement("CREATE POLICY payslip_lines_company_isolation ON pay.payslip_lines
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

        // ===== Trigger: Update payslip totals when lines change =====
        DB::statement("
            CREATE OR REPLACE FUNCTION pay.update_payslip_totals()
            RETURNS TRIGGER AS $$
            BEGIN
                UPDATE pay.payslips
                SET
                    total_earnings = COALESCE((
                        SELECT SUM(amount) FROM pay.payslip_lines
                        WHERE payslip_id = COALESCE(NEW.payslip_id, OLD.payslip_id)
                        AND line_type = 'earning'
                    ), 0),
                    total_deductions = COALESCE((
                        SELECT SUM(amount) FROM pay.payslip_lines
                        WHERE payslip_id = COALESCE(NEW.payslip_id, OLD.payslip_id)
                        AND line_type = 'deduction'
                    ), 0),
                    employer_costs = COALESCE((
                        SELECT SUM(amount) FROM pay.payslip_lines
                        WHERE payslip_id = COALESCE(NEW.payslip_id, OLD.payslip_id)
                        AND line_type = 'employer'
                    ), 0),
                    gross_pay = COALESCE((
                        SELECT SUM(amount) FROM pay.payslip_lines
                        WHERE payslip_id = COALESCE(NEW.payslip_id, OLD.payslip_id)
                        AND line_type = 'earning'
                    ), 0),
                    net_pay = COALESCE((
                        SELECT SUM(amount) FROM pay.payslip_lines
                        WHERE payslip_id = COALESCE(NEW.payslip_id, OLD.payslip_id)
                        AND line_type = 'earning'
                    ), 0) - COALESCE((
                        SELECT SUM(amount) FROM pay.payslip_lines
                        WHERE payslip_id = COALESCE(NEW.payslip_id, OLD.payslip_id)
                        AND line_type = 'deduction'
                    ), 0),
                    updated_at = NOW()
                WHERE id = COALESCE(NEW.payslip_id, OLD.payslip_id);

                RETURN COALESCE(NEW, OLD);
            END;
            $$ LANGUAGE plpgsql;
        ");

        DB::statement("
            CREATE TRIGGER trg_payslip_lines_update_totals
            AFTER INSERT OR UPDATE OR DELETE ON pay.payslip_lines
            FOR EACH ROW
            EXECUTE FUNCTION pay.update_payslip_totals();
        ");
    }

    public function down(): void
    {
        // Drop trigger and function
        DB::statement('DROP TRIGGER IF EXISTS trg_payslip_lines_update_totals ON pay.payslip_lines');
        DB::statement('DROP FUNCTION IF EXISTS pay.update_payslip_totals()');

        // Drop tables in reverse order (respecting FK dependencies)
        Schema::dropIfExists('pay.payslip_lines');
        Schema::dropIfExists('pay.payslips');
        Schema::dropIfExists('pay.payroll_periods');
        Schema::dropIfExists('pay.leave_requests');
        Schema::dropIfExists('pay.leave_types');
        Schema::dropIfExists('pay.employee_benefits');
        Schema::dropIfExists('pay.benefit_plans');
        Schema::dropIfExists('pay.deduction_types');
        Schema::dropIfExists('pay.earning_types');
        Schema::dropIfExists('pay.employees');

        // Drop schema
        DB::statement('DROP SCHEMA IF EXISTS pay CASCADE');
    }
};
