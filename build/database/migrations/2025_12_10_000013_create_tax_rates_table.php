<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax.tax_rates', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('jurisdiction_id');
            $table->string('code', 50);
            $table->string('name', 255);
            $table->decimal('rate', 8, 4);
            $table->string('tax_type', 30)->default('sales');
            $table->boolean('is_compound')->default(false);
            $table->integer('compound_priority')->default(0);
            $table->uuid('gl_account_id')->nullable();
            $table->uuid('recoverable_account_id')->nullable();
            $table->date('effective_from')->default(DB::raw('CURRENT_DATE'));
            $table->date('effective_to')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('jurisdiction_id')->references('id')->on('tax.jurisdictions')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('gl_account_id')->references('id')->on('acct.accounts')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('recoverable_account_id')->references('id')->on('acct.accounts')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('updated_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();

            $table->unique(['company_id', 'code', 'effective_from'])->whereNull('deleted_at');
            $table->index('company_id');
            $table->index('jurisdiction_id');
            $table->index(['company_id', 'tax_type', 'is_active']);
            $table->index(['company_id', 'is_default']);
        });

        DB::statement("ALTER TABLE tax.tax_rates ENABLE ROW LEVEL SECURITY");
        DB::statement("
            CREATE POLICY tax_rates_policy ON tax.tax_rates
            FOR ALL USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");

        // Add check constraints
        DB::statement("
            ALTER TABLE tax.tax_rates
            ADD CONSTRAINT tax_rates_rate_chk
            CHECK (rate >= 0 AND rate <= 100)
        ");

        DB::statement("
            ALTER TABLE tax.tax_rates
            ADD CONSTRAINT tax_rates_tax_type_chk
            CHECK (tax_type IN ('sales', 'purchase', 'withholding', 'both'))
        ");

        DB::statement("
            ALTER TABLE tax.tax_rates
            ADD CONSTRAINT tax_rates_effective_dates_chk
            CHECK (effective_to IS NULL OR effective_to > effective_from)
        ");

        DB::statement("
            ALTER TABLE tax.tax_rates
            ADD CONSTRAINT tax_rates_compound_priority_chk
            CHECK (compound_priority >= 0)
        ");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS tax_rates_policy ON tax.tax_rates');
        Schema::dropIfExists('tax.tax_rates');
    }
};