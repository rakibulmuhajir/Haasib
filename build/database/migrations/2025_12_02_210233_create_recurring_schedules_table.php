<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('acct.recurring_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('customer_id');
            $table->string('name', 255);
            $table->string('frequency', 20); // daily, weekly, monthly, quarterly, yearly
            $table->integer('interval')->default(1);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('next_invoice_date');
            $table->timestamp('last_generated_at')->nullable();
            $table->jsonb('template_data');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete();
            $table->foreign('customer_id')->references('id')->on('acct.customers')->restrictOnDelete();

            // Indexes
            $table->index(['company_id']);
            $table->index(['customer_id']);
            $table->index(['company_id', 'is_active', 'next_invoice_date']);
            $table->index(['company_id', 'is_active']);
            $table->index(['next_invoice_date']);
            $table->index(['start_date']);
            $table->index(['end_date']);
        });

        // Enable RLS
        DB::statement('ALTER TABLE acct.recurring_schedules ENABLE ROW LEVEL SECURITY');

        // Create RLS policies
        DB::statement("
            CREATE POLICY recurring_schedules_company_policy ON acct.recurring_schedules
            FOR ALL
            USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
            WITH CHECK (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");

        // Add constraints
        DB::statement('
            ALTER TABLE acct.recurring_schedules
            ADD CONSTRAINT recurring_schedules_interval_positive
            CHECK (interval > 0)
        ');

        DB::statement('
            ALTER TABLE acct.recurring_schedules
            ADD CONSTRAINT recurring_schedules_valid_frequency
            CHECK (frequency IN (\'daily\', \'weekly\', \'monthly\', \'quarterly\', \'yearly\'))
        ');

        DB::statement('
            ALTER TABLE acct.recurring_schedules
            ADD CONSTRAINT recurring_schedules_date_logic
            CHECK (
                end_date IS NULL
                OR end_date >= start_date
            )
        ');

        DB::statement('
            ALTER TABLE acct.recurring_schedules
            ADD CONSTRAINT recurring_schedules_next_date_logic
            CHECK (
                next_invoice_date >= start_date
                AND (
                    end_date IS NULL
                    OR next_invoice_date <= end_date
                )
            )
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS recurring_schedules_company_policy ON acct.recurring_schedules');
        DB::statement('ALTER TABLE acct.recurring_schedules DISABLE ROW LEVEL SECURITY');
        Schema::dropIfExists('acct.recurring_schedules');
    }
};