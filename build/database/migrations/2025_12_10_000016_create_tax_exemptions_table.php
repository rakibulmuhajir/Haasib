<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax.tax_exemptions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->string('code', 50);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('exemption_type', 30)->default('full');
            $table->decimal('override_rate', 8, 4)->nullable();
            $table->boolean('requires_certificate')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();

            $table->unique(['company_id', 'code'])->whereNull('deleted_at');
            $table->index('company_id');
            $table->index(['company_id', 'is_active']);
        });

        DB::statement("ALTER TABLE tax.tax_exemptions ENABLE ROW LEVEL SECURITY");
        DB::statement("
            CREATE POLICY tax_exemptions_policy ON tax.tax_exemptions
            FOR ALL USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");

        // Add check constraints
        DB::statement("
            ALTER TABLE tax.tax_exemptions
            ADD CONSTRAINT tax_exemptions_exemption_type_chk
            CHECK (exemption_type IN ('full', 'partial', 'rate_override'))
        ");

        DB::statement("
            ALTER TABLE tax.tax_exemptions
            ADD CONSTRAINT tax_exemptions_override_rate_chk
            CHECK (override_rate IS NULL OR (override_rate >= 0 AND override_rate <= 100))
        ");

        DB::statement("
            ALTER TABLE tax.tax_exemptions
            ADD CONSTRAINT tax_exemptions_override_rate_required_chk
            CHECK (
                exemption_type != 'rate_override' OR
                (exemption_type = 'rate_override' AND override_rate IS NOT NULL)
            )
        ");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS tax_exemptions_policy ON tax.tax_exemptions');
        Schema::dropIfExists('tax.tax_exemptions');
    }
};