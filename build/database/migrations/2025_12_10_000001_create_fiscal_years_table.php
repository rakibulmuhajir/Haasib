<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acct.fiscal_years', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->string('name', 100);
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_current')->default(false);
            $table->boolean('is_closed')->default(false);
            $table->string('status', 20)->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->uuid('closed_by_user_id')->nullable();
            $table->uuid('retained_earnings_account_id')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('closed_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('updated_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('retained_earnings_account_id')->references('id')->on('acct.accounts')->nullOnDelete()->cascadeOnUpdate();

            $table->unique(['company_id', 'name']);
            $table->unique(['company_id', 'start_date']);
            $table->index('company_id');
            $table->index(['company_id', 'is_current']);
        });

        DB::statement("
            ALTER TABLE acct.fiscal_years
            ADD CONSTRAINT fiscal_years_date_chk
            CHECK (end_date > start_date)
        ");

        DB::statement("ALTER TABLE acct.fiscal_years ENABLE ROW LEVEL SECURITY");
        DB::statement("
            CREATE POLICY fiscal_years_policy ON acct.fiscal_years
            FOR ALL
            USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR coalesce(current_setting('app.is_super_admin', true)::boolean, false)
            )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('acct.fiscal_years');
    }
};
