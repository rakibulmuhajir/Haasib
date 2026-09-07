<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        Schema::create('umrah.pricing_categories', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->index(['company_id', 'is_active', 'name']);
        });

        DB::statement('CREATE UNIQUE INDEX pricing_categories_company_name_unique
            ON umrah.pricing_categories (company_id, lower(name))
            WHERE deleted_at IS NULL');

        Schema::table('umrah.agents', function (Blueprint $table) {
            $table->uuid('pricing_category_id')->nullable();
            $table->foreign('pricing_category_id')->references('id')->on('umrah.pricing_categories')->restrictOnDelete()->cascadeOnUpdate();
            $table->index(['company_id', 'pricing_category_id']);
        });

        Schema::create('umrah.commercial_rates', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->string('service_type', 30);
            $table->uuid('visa_vendor_id')->nullable();
            $table->uuid('transport_fare_id')->nullable();
            $table->uuid('hotel_room_rate_id')->nullable();
            $table->string('scope_type', 20);
            $table->uuid('pricing_category_id')->nullable();
            $table->uuid('agent_id')->nullable();
            $table->string('calculation_type', 30);
            $table->decimal('amount', 15, 2)->nullable();
            $table->decimal('percentage', 9, 4)->nullable();
            $table->decimal('cost_amount', 15, 2)->nullable();
            $table->char('currency', 3);
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('visa_vendor_id')->references('id')->on('umrah.visa_vendors')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('transport_fare_id')->references('id')->on('umrah.transport_fares')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('hotel_room_rate_id')->references('id')->on('umrah.hotel_room_rates')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('pricing_category_id')->references('id')->on('umrah.pricing_categories')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('agent_id')->references('id')->on('umrah.agents')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('currency')->references('code')->on('public.currencies')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();

            $table->index(['company_id', 'service_type', 'effective_from', 'effective_until'], 'commercial_rates_resolution_index');
            $table->index(['company_id', 'visa_vendor_id']);
            $table->index(['company_id', 'transport_fare_id']);
            $table->index(['company_id', 'hotel_room_rate_id']);
            $table->index(['company_id', 'pricing_category_id']);
            $table->index(['company_id', 'agent_id']);
        });

        DB::statement("ALTER TABLE umrah.commercial_rates ADD CONSTRAINT commercial_rates_service_type_check
            CHECK (service_type IN ('visa_adult', 'visa_child', 'standard_transport', 'transport_fare', 'hotel_room'))");
        DB::statement("ALTER TABLE umrah.commercial_rates ADD CONSTRAINT commercial_rates_scope_type_check
            CHECK (scope_type IN ('default', 'category', 'agent'))");
        DB::statement("ALTER TABLE umrah.commercial_rates ADD CONSTRAINT commercial_rates_calculation_type_check
            CHECK (calculation_type IN ('set_price', 'discount_amount', 'discount_percentage', 'markup_amount', 'markup_percentage'))");
        DB::statement("ALTER TABLE umrah.commercial_rates ADD CONSTRAINT commercial_rates_target_check CHECK (
            (service_type IN ('visa_adult', 'visa_child', 'standard_transport') AND visa_vendor_id IS NOT NULL AND transport_fare_id IS NULL AND hotel_room_rate_id IS NULL)
            OR (service_type = 'transport_fare' AND visa_vendor_id IS NULL AND transport_fare_id IS NOT NULL AND hotel_room_rate_id IS NULL)
            OR (service_type = 'hotel_room' AND visa_vendor_id IS NULL AND transport_fare_id IS NULL AND hotel_room_rate_id IS NOT NULL)
        )");
        DB::statement("ALTER TABLE umrah.commercial_rates ADD CONSTRAINT commercial_rates_scope_target_check CHECK (
            (scope_type = 'default' AND pricing_category_id IS NULL AND agent_id IS NULL)
            OR (scope_type = 'category' AND pricing_category_id IS NOT NULL AND agent_id IS NULL)
            OR (scope_type = 'agent' AND pricing_category_id IS NULL AND agent_id IS NOT NULL)
        )");
        DB::statement("ALTER TABLE umrah.commercial_rates ADD CONSTRAINT commercial_rates_values_check CHECK (
            ((calculation_type IN ('set_price', 'discount_amount', 'markup_amount')) AND amount IS NOT NULL AND amount >= 0 AND percentage IS NULL)
            OR ((calculation_type = 'discount_percentage') AND amount IS NULL AND percentage BETWEEN 0 AND 100)
            OR ((calculation_type = 'markup_percentage') AND amount IS NULL AND percentage BETWEEN 0 AND 1000)
        )");
        DB::statement("ALTER TABLE umrah.commercial_rates ADD CONSTRAINT commercial_rates_default_cost_check CHECK (
            (scope_type = 'default' AND calculation_type = 'set_price')
            OR (scope_type <> 'default' AND cost_amount IS NULL)
        )");
        DB::statement('ALTER TABLE umrah.commercial_rates ADD CONSTRAINT commercial_rates_cost_nonnegative_check CHECK (cost_amount IS NULL OR cost_amount >= 0)');
        DB::statement('ALTER TABLE umrah.commercial_rates ADD CONSTRAINT commercial_rates_dates_check CHECK (effective_until IS NULL OR effective_until >= effective_from)');

        DB::statement("ALTER TABLE umrah.commercial_rates ADD CONSTRAINT commercial_rates_no_overlap
            EXCLUDE USING gist (
                company_id WITH =,
                service_type WITH =,
                (COALESCE(visa_vendor_id, transport_fare_id, hotel_room_rate_id)) WITH =,
                scope_type WITH =,
                (COALESCE(pricing_category_id, agent_id, '00000000-0000-0000-0000-000000000000'::uuid)) WITH =,
                (daterange(effective_from, COALESCE(effective_until + 1, 'infinity'::date), '[)')) WITH &&
            ) WHERE (is_active AND deleted_at IS NULL)");

        foreach (['pricing_categories', 'commercial_rates'] as $table) {
            DB::statement("ALTER TABLE umrah.{$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE umrah.{$table} FORCE ROW LEVEL SECURITY");
            DB::statement("CREATE POLICY {$table}_company_isolation ON umrah.{$table}
                FOR ALL
                USING (company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid
                    OR COALESCE(NULLIF(current_setting('app.is_super_admin', true), '')::boolean, false) = true)
                WITH CHECK (company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid
                    OR COALESCE(NULLIF(current_setting('app.is_super_admin', true), '')::boolean, false) = true)");
        }

        Schema::table('umrah.visa_groups', function (Blueprint $table) {
            $table->jsonb('pricing_snapshot')->default(DB::raw("'{}'::jsonb"));
        });
    }

    public function down(): void
    {
        Schema::table('umrah.visa_groups', function (Blueprint $table) {
            $table->dropColumn('pricing_snapshot');
        });

        DB::statement('DROP POLICY IF EXISTS commercial_rates_company_isolation ON umrah.commercial_rates');
        Schema::dropIfExists('umrah.commercial_rates');

        Schema::table('umrah.agents', function (Blueprint $table) {
            $table->dropForeign(['pricing_category_id']);
            $table->dropIndex(['company_id', 'pricing_category_id']);
            $table->dropColumn('pricing_category_id');
        });

        DB::statement('DROP POLICY IF EXISTS pricing_categories_company_isolation ON umrah.pricing_categories');
        Schema::dropIfExists('umrah.pricing_categories');
    }
};
