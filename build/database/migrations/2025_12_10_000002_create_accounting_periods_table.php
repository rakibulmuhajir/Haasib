<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acct.accounting_periods', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('fiscal_year_id');
            $table->string('name', 100);
            $table->integer('period_number');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('period_type', 20)->default('monthly');
            $table->boolean('is_closed')->default(false);
            $table->boolean('is_adjustment')->default(false);
            $table->timestamp('closed_at')->nullable();
            $table->uuid('closed_by_user_id')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('fiscal_year_id')->references('id')->on('acct.fiscal_years')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('closed_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('updated_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();

            $table->unique(['fiscal_year_id', 'period_number']);
            $table->unique(['company_id', 'start_date']);
            $table->index('company_id');
            $table->index('fiscal_year_id');
            $table->index(['company_id', 'is_closed']);
        });

        DB::statement("
            ALTER TABLE acct.accounting_periods
            ADD CONSTRAINT accounting_periods_date_chk
            CHECK (end_date > start_date)
        ");

        DB::statement("ALTER TABLE acct.accounting_periods ENABLE ROW LEVEL SECURITY");
        DB::statement("
            CREATE POLICY accounting_periods_policy ON acct.accounting_periods
            FOR ALL
            USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR coalesce(current_setting('app.is_super_admin', true)::boolean, false)
            )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('acct.accounting_periods');
    }
};
