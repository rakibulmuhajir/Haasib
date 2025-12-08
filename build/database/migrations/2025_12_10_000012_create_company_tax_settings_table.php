<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax.company_tax_settings', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->boolean('tax_enabled')->default(false);
            $table->uuid('default_jurisdiction_id')->nullable();
            $table->uuid('default_sales_tax_rate_id')->nullable();
            $table->uuid('default_purchase_tax_rate_id')->nullable();
            $table->boolean('price_includes_tax')->default(false);
            $table->string('rounding_mode', 20)->default('half_up');
            $table->smallInteger('rounding_precision')->default(2);
            $table->string('tax_number_label', 50)->default('Tax ID');
            $table->boolean('show_tax_column')->default(true);
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('default_jurisdiction_id')->references('id')->on('tax.jurisdictions')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('default_sales_tax_rate_id')->references('id')->on('tax.tax_rates')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('default_purchase_tax_rate_id')->references('id')->on('tax.tax_rates')->nullOnDelete()->cascadeOnUpdate();

            $table->unique('company_id');
        });

        DB::statement("ALTER TABLE tax.company_tax_settings ENABLE ROW LEVEL SECURITY");
        DB::statement("
            CREATE POLICY company_tax_settings_policy ON tax.company_tax_settings
            FOR ALL USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");

        // Add check constraint for rounding precision
        DB::statement("
            ALTER TABLE tax.company_tax_settings
            ADD CONSTRAINT company_tax_settings_rounding_precision_chk
            CHECK (rounding_precision >= 0 AND rounding_precision <= 6)
        ");

        // Add check constraint for rounding mode
        DB::statement("
            ALTER TABLE tax.company_tax_settings
            ADD CONSTRAINT company_tax_settings_rounding_mode_chk
            CHECK (rounding_mode IN ('half_up', 'half_down', 'floor', 'ceiling', 'bankers'))
        ");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS company_tax_settings_policy ON tax.company_tax_settings');
        Schema::dropIfExists('tax.company_tax_settings');
    }
};
