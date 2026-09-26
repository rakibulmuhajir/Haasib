<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A per-customer, per-fuel-item discount: a transporter gets Rs 3/L on diesel and nothing
 * on petrol. Read through CustomerFuelDiscountService only -- every place that prices a
 * discounted sale (Fuel -> Sales standalone form, a Daily Close manual credit row) goes
 * through that one service so the maths never drifts between entry points.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuel.customer_fuel_discounts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('customer_id');
            $table->uuid('item_id');
            $table->string('discount_type', 20); // percent, per_litre
            $table->decimal('value', 12, 4);
            $table->timestamps();

            $table->foreign('company_id')
                ->references('id')->on('auth.companies')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('customer_id')
                ->references('id')->on('acct.customers')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('item_id')
                ->references('id')->on('inv.items')
                ->cascadeOnDelete()->cascadeOnUpdate();

            $table->unique(['company_id', 'customer_id', 'item_id']);
            $table->index('company_id');
        });

        DB::statement("ALTER TABLE fuel.customer_fuel_discounts ADD CONSTRAINT customer_fuel_discounts_type_check
            CHECK (discount_type IN ('percent', 'per_litre'))");
        DB::statement('ALTER TABLE fuel.customer_fuel_discounts ADD CONSTRAINT customer_fuel_discounts_value_positive
            CHECK (value > 0)');
        DB::statement("ALTER TABLE fuel.customer_fuel_discounts ADD CONSTRAINT customer_fuel_discounts_percent_max
            CHECK (discount_type <> 'percent' OR value <= 100)");

        DB::statement('ALTER TABLE fuel.customer_fuel_discounts ENABLE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY customer_fuel_discounts_company_isolation ON fuel.customer_fuel_discounts
            FOR ALL
            USING (
                company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel.customer_fuel_discounts');
    }
};
