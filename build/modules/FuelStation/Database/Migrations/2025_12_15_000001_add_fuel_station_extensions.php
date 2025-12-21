<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Extends existing tables for fuel station support:
 * - inv.items: Add fuel_category for fuel type classification, avg_cost for WAC
 * - inv.warehouses: Add tank support with capacity, linked_item_id
 * - acct.customers: Add non-exclusive customer type flags
 * - acct.invoices: Add sale_type, pump_id, attendant_transit for fuel sales
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─────────────────────────────────────────────────────────────────────
        // inv.items - Add fuel_category and avg_cost
        // ─────────────────────────────────────────────────────────────────────
        Schema::table('inv.items', function (Blueprint $table) {
            // Fuel category: null for non-fuel items
            $table->string('fuel_category', 20)->nullable()->after('item_type');

            // Weighted Average Cost for inventory costing (updated on each purchase)
            $table->decimal('avg_cost', 10, 4)->nullable()->after('cost_price');
        });

        // Add check constraint for fuel_category enum
        DB::statement("ALTER TABLE inv.items ADD CONSTRAINT items_fuel_category_check
            CHECK (fuel_category IS NULL OR fuel_category IN ('petrol', 'diesel', 'high_octane'))");

        // Index for fuel items lookup
        DB::statement('CREATE INDEX items_fuel_category_idx ON inv.items (company_id, fuel_category) WHERE fuel_category IS NOT NULL');

        // ─────────────────────────────────────────────────────────────────────
        // inv.warehouses - Add tank support
        // ─────────────────────────────────────────────────────────────────────
        Schema::table('inv.warehouses', function (Blueprint $table) {
            // Warehouse type: standard or tank
            $table->string('warehouse_type', 20)->default('standard')->after('name');

            // Tank capacity in liters
            $table->decimal('capacity', 12, 2)->nullable()->after('warehouse_type');

            // Low level alert threshold
            $table->decimal('low_level_alert', 12, 2)->nullable()->after('capacity');

            // Linked fuel item (for tanks)
            $table->uuid('linked_item_id')->nullable()->after('low_level_alert');

            // FK to inv.items
            $table->foreign('linked_item_id')
                ->references('id')->on('inv.items')
                ->nullOnDelete()->cascadeOnUpdate();
        });

        // Add check constraint for warehouse_type enum
        DB::statement("ALTER TABLE inv.warehouses ADD CONSTRAINT warehouses_type_check
            CHECK (warehouse_type IN ('standard', 'tank'))");

        // Constraint: tanks must have linked_item_id and capacity
        DB::statement("ALTER TABLE inv.warehouses ADD CONSTRAINT tank_requires_item_and_capacity
            CHECK (warehouse_type != 'tank' OR (linked_item_id IS NOT NULL AND capacity IS NOT NULL))");

        // Index for tank lookups
        DB::statement("CREATE INDEX warehouses_tank_idx ON inv.warehouses (company_id, warehouse_type) WHERE warehouse_type = 'tank'");

        // ─────────────────────────────────────────────────────────────────────
        // acct.customers - Add fuel station customer type flags
        // ─────────────────────────────────────────────────────────────────────
        Schema::table('acct.customers', function (Blueprint $table) {
            // Non-exclusive customer type flags (customers can be multiple types)
            $table->boolean('is_credit_customer')->default(false)->after('is_active');
            $table->boolean('is_amanat_holder')->default(false)->after('is_credit_customer');
            $table->boolean('is_investor')->default(false)->after('is_amanat_holder');

            // Note: credit_limit already exists in customers table

            // Customer relationship (owner, employee, external)
            $table->string('relationship', 50)->nullable()->after('is_investor');

            // Pakistani national ID (for investors/amanat holders)
            $table->string('cnic', 20)->nullable()->after('relationship');

            // Current amanat (trust deposit) balance
            $table->decimal('amanat_balance', 15, 2)->default(0)->after('cnic');
        });

        // Add check constraint for relationship enum
        DB::statement("ALTER TABLE acct.customers ADD CONSTRAINT customers_relationship_check
            CHECK (relationship IS NULL OR relationship IN ('owner', 'employee', 'external'))");

        // Indexes for customer type lookups
        DB::statement('CREATE INDEX customers_credit_idx ON acct.customers (company_id) WHERE is_credit_customer = true');
        DB::statement('CREATE INDEX customers_amanat_idx ON acct.customers (company_id) WHERE is_amanat_holder = true');
        DB::statement('CREATE INDEX customers_investor_idx ON acct.customers (company_id) WHERE is_investor = true');

        // ─────────────────────────────────────────────────────────────────────
        // acct.invoices - Add fuel sale fields
        // ─────────────────────────────────────────────────────────────────────
        Schema::table('acct.invoices', function (Blueprint $table) {
            // Sale type for fuel stations
            $table->string('sale_type', 20)->nullable()->after('status');

            // Pump used for the sale (FK will be added when fuel.pumps table exists)
            $table->uuid('pump_id')->nullable()->after('sale_type');

            // Whether cash is in attendant transit (not yet handed over)
            $table->boolean('attendant_transit')->default(false)->after('pump_id');

            // Discount reason for audit trail
            $table->string('discount_reason', 50)->nullable()->after('attendant_transit');
        });

        // Add check constraint for sale_type enum
        DB::statement("ALTER TABLE acct.invoices ADD CONSTRAINT invoices_sale_type_check
            CHECK (sale_type IS NULL OR sale_type IN ('retail', 'bulk', 'credit', 'amanat', 'investor', 'parco_card'))");

        // Index for fuel sale type lookups
        DB::statement('CREATE INDEX invoices_sale_type_idx ON acct.invoices (company_id, sale_type) WHERE sale_type IS NOT NULL');
        DB::statement('CREATE INDEX invoices_attendant_transit_idx ON acct.invoices (company_id) WHERE attendant_transit = true');
    }

    public function down(): void
    {
        // ─────────────────────────────────────────────────────────────────────
        // acct.invoices - Remove fuel sale fields
        // ─────────────────────────────────────────────────────────────────────
        DB::statement('DROP INDEX IF EXISTS acct.invoices_attendant_transit_idx');
        DB::statement('DROP INDEX IF EXISTS acct.invoices_sale_type_idx');
        DB::statement('ALTER TABLE acct.invoices DROP CONSTRAINT IF EXISTS invoices_sale_type_check');

        Schema::table('acct.invoices', function (Blueprint $table) {
            $table->dropColumn(['sale_type', 'pump_id', 'attendant_transit', 'discount_reason']);
        });

        // ─────────────────────────────────────────────────────────────────────
        // acct.customers - Remove fuel station customer fields
        // ─────────────────────────────────────────────────────────────────────
        DB::statement('DROP INDEX IF EXISTS acct.customers_investor_idx');
        DB::statement('DROP INDEX IF EXISTS acct.customers_amanat_idx');
        DB::statement('DROP INDEX IF EXISTS acct.customers_credit_idx');
        DB::statement('ALTER TABLE acct.customers DROP CONSTRAINT IF EXISTS customers_relationship_check');

        Schema::table('acct.customers', function (Blueprint $table) {
            $table->dropColumn([
                'is_credit_customer',
                'is_amanat_holder',
                'is_investor',
                // Note: credit_limit already existed before this migration
                'relationship',
                'cnic',
                'amanat_balance',
            ]);
        });

        // ─────────────────────────────────────────────────────────────────────
        // inv.warehouses - Remove tank support
        // ─────────────────────────────────────────────────────────────────────
        DB::statement('DROP INDEX IF EXISTS inv.warehouses_tank_idx');
        DB::statement('ALTER TABLE inv.warehouses DROP CONSTRAINT IF EXISTS tank_requires_item_and_capacity');
        DB::statement('ALTER TABLE inv.warehouses DROP CONSTRAINT IF EXISTS warehouses_type_check');

        Schema::table('inv.warehouses', function (Blueprint $table) {
            $table->dropForeign(['linked_item_id']);
            $table->dropColumn(['warehouse_type', 'capacity', 'low_level_alert', 'linked_item_id']);
        });

        // ─────────────────────────────────────────────────────────────────────
        // inv.items - Remove fuel fields
        // ─────────────────────────────────────────────────────────────────────
        DB::statement('DROP INDEX IF EXISTS inv.items_fuel_category_idx');
        DB::statement('ALTER TABLE inv.items DROP CONSTRAINT IF EXISTS items_fuel_category_check');

        Schema::table('inv.items', function (Blueprint $table) {
            $table->dropColumn(['fuel_category', 'avg_cost']);
        });
    }
};
