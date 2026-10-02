<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hours worked, logged per day, for employees paid by the hour. The month's payroll pays the
 * month's total hours x the employee's hourly rate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pay.time_entries', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('employee_id');
            $table->date('work_date');
            $table->decimal('hours', 6, 2);
            $table->string('notes', 255)->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('company_id')
                ->references('id')->on('auth.companies')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('employee_id')
                ->references('id')->on('pay.employees')
                ->restrictOnDelete()->cascadeOnUpdate();

            $table->index(['company_id', 'employee_id', 'work_date']);
        });

        DB::statement('ALTER TABLE pay.time_entries ADD CONSTRAINT time_entries_hours_check CHECK (hours > 0)');

        $tableName = 'time_entries';
        DB::statement("ALTER TABLE pay.{$tableName} ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE pay.{$tableName} FORCE ROW LEVEL SECURITY");

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

        DB::statement("CREATE POLICY {$tableName}_company_isolation ON pay.{$tableName}
            FOR ALL
            USING (company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid)
            WITH CHECK (company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('pay.time_entries');
    }
};
