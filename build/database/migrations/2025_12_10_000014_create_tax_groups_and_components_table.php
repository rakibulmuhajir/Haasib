<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax.tax_groups', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('jurisdiction_id');
            $table->string('code', 50);
            $table->string('name', 255);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('jurisdiction_id')->references('id')->on('tax.jurisdictions')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();

            $table->unique(['company_id', 'code'])->whereNull('deleted_at');
            $table->index('company_id');
            $table->index(['company_id', 'is_active']);
        });

        Schema::create('tax.tax_group_components', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('tax_group_id');
            $table->uuid('tax_rate_id');
            $table->smallInteger('priority')->default(1);
            $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));

            $table->foreign('tax_group_id')->references('id')->on('tax.tax_groups')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('tax_rate_id')->references('id')->on('tax.tax_rates')->restrictOnDelete()->cascadeOnUpdate();

            $table->unique(['tax_group_id', 'tax_rate_id']);
            $table->index('tax_group_id');
            $table->index('tax_rate_id');
        });

        DB::statement("ALTER TABLE tax.tax_groups ENABLE ROW LEVEL SECURITY");
        DB::statement("
            CREATE POLICY tax_groups_policy ON tax.tax_groups
            FOR ALL USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");

        // Enable RLS for tax group components
        DB::statement("ALTER TABLE tax.tax_group_components ENABLE ROW LEVEL SECURITY");

        // Policy for tax group components based on parent group's company
        DB::statement("
            CREATE POLICY tax_group_components_policy ON tax.tax_group_components
            FOR ALL USING (
                EXISTS (
                    SELECT 1 FROM tax.tax_groups
                    WHERE tax_groups.id = tax_group_components.tax_group_id
                    AND tax_groups.company_id = current_setting('app.current_company_id', true)::uuid
                ) OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS tax_groups_policy ON tax.tax_groups');
        DB::statement('DROP POLICY IF EXISTS tax_group_components_policy ON tax.tax_group_components');
        Schema::dropIfExists('tax.tax_group_components');
        Schema::dropIfExists('tax.tax_groups');
    }
};