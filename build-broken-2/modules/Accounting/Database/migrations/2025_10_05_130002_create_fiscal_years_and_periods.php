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
        Schema::create('acct.fiscal_years', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('generate_uuid()'));
            $table->uuid('company_id');
            $table->string('name', 100); // e.g., "FY 2025"
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(false);
            $table->boolean('is_locked')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('company_id')
                ->references('id')
                ->on('auth.companies')
                ->onDelete('cascade');

            $table->unique(['company_id', 'name']);
            $table->index(['company_id', 'is_active']);
            $table->index(['company_id', 'start_date', 'end_date']);
        });

        Schema::create('acct.accounting_periods', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('generate_uuid()'));
            $table->uuid('fiscal_year_id');
            $table->string('name', 100); // e.g., "Jan 2025", "Q1 2025"
            $table->date('start_date');
            $table->date('end_date');
            $table->string('period_type', 20); // month, quarter, year
            $table->integer('period_number'); // 1-12 for months, 1-4 for quarters
            $table->string('status', 20)->default('future'); // future, open, closed, locked
            $table->uuid('closed_by')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('closing_notes')->nullable();
            $table->timestamps();

            $table->foreign('fiscal_year_id')
                ->references('id')
                ->on('acct.fiscal_years')
                ->onDelete('cascade');

            $table->foreign('closed_by')
                ->references('id')
                ->on('auth.users')
                ->onDelete('set null');

            $table->unique(['fiscal_year_id', 'period_number', 'period_type']);
            $table->index(['fiscal_year_id', 'status']);
            $table->index(['status', 'start_date']);
        });

        // Enable RLS
        DB::statement('ALTER TABLE acct.fiscal_years ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE acct.accounting_periods ENABLE ROW LEVEL SECURITY');

        // Force RLS to ensure even table owner bypasses policies
        DB::statement('ALTER TABLE acct.fiscal_years FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE acct.accounting_periods FORCE ROW LEVEL SECURITY');

        // Create RLS policies
        DB::statement('
            CREATE POLICY fiscal_years_company_policy ON acct.fiscal_years
            FOR ALL
            TO authenticated
            USING (company_id = acct.company_id_policy()::uuid)
            WITH CHECK (company_id = acct.company_id_policy()::uuid)
        ');

        DB::statement('
            CREATE POLICY accounting_periods_company_policy ON acct.accounting_periods
            FOR ALL
            TO authenticated
            USING (
                fiscal_year_id IN (
                    SELECT id FROM acct.fiscal_years
                    WHERE company_id = acct.company_id_policy()::uuid
                )
            )
            WITH CHECK (
                fiscal_year_id IN (
                    SELECT id FROM acct.fiscal_years
                    WHERE company_id = acct.company_id_policy()::uuid
                )
            )
        ');

        // Create trigger to prevent modifications to closed periods
        DB::statement('
            CREATE OR REPLACE FUNCTION acct.prevent_closed_period_modifications()
            RETURNS trigger AS $$
            BEGIN
                IF TG_OP = \'UPDATE\' AND OLD.status = \'closed\' AND NEW.status != \'closed\' THEN
                    RAISE EXCEPTION \'Cannot modify closed accounting period\';
                END IF;
                IF TG_OP = \'DELETE\' AND OLD.status = \'closed\' THEN
                    RAISE EXCEPTION \'Cannot delete closed accounting period\';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ');

        DB::statement('
            CREATE TRIGGER accounting_periods_closed_check
            BEFORE UPDATE OR DELETE ON acct.accounting_periods
            FOR EACH ROW EXECUTE FUNCTION acct.prevent_closed_period_modifications();
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop trigger
        DB::statement('DROP TRIGGER IF EXISTS accounting_periods_closed_check ON acct.accounting_periods');
        DB::statement('DROP FUNCTION IF EXISTS acct.prevent_closed_period_modifications()');

        // Drop policies
        DB::statement('DROP POLICY IF EXISTS fiscal_years_company_policy ON acct.fiscal_years');
        DB::statement('DROP POLICY IF EXISTS accounting_periods_company_policy ON acct.accounting_periods');

        // Disable RLS
        DB::statement('ALTER TABLE acct.fiscal_years DISABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE acct.accounting_periods DISABLE ROW LEVEL SECURITY');

        // Drop tables
        Schema::dropIfExists('acct.accounting_periods');
        Schema::dropIfExists('acct.fiscal_years');
    }
};
