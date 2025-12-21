<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create inventory schema with all related tables:
     * - item_categories: hierarchical product categories
     * - items: products and services master
     * - warehouses: storage locations
     * - stock_levels: current quantity per item per warehouse
     * - stock_movements: immutable log of all inventory changes
     * - cost_policies: costing method configuration per company
     * - item_costs: running cost summary (for WA method)
     * - cost_layers: cost layers for FIFO costing
     * - cogs_entries: COGS records at time of sale
     */
    public function up(): void
    {
        // Create the inv schema (and keep fresh installs reliable in multi-schema PostgreSQL).
        // Laravel's migrate:fresh can leave behind tables in non-default schemas; if that happens,
        // the safest reset is dropping the affected schemas with CASCADE, then recreating them.
        $hasInvTables = (bool) (DB::selectOne("
            SELECT EXISTS (
                SELECT 1
                FROM information_schema.tables
                WHERE table_schema = 'inv'
            ) AS exists
        ")?->exists ?? false);

        $hasFuelTables = (bool) (DB::selectOne("
            SELECT EXISTS (
                SELECT 1
                FROM information_schema.tables
                WHERE table_schema = 'fuel'
            ) AS exists
        ")?->exists ?? false);

        if ($hasInvTables || $hasFuelTables) {
            // If fuel tables exist, they may hold FKs into inv.* (e.g. fuel.pumps → inv.warehouses).
            // Dropping schemas CASCADE ensures all dependent constraints are removed cleanly.
            DB::statement('DROP SCHEMA IF EXISTS fuel CASCADE');
            DB::statement('DROP SCHEMA IF EXISTS inv CASCADE');
        }

        DB::statement('CREATE SCHEMA IF NOT EXISTS inv');

        // ─────────────────────────────────────────────────────────────────────
        // inv.item_categories - Hierarchical product categories
        // ─────────────────────────────────────────────────────────────────────
        Schema::create('inv.item_categories', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('parent_id')->nullable();
            $table->string('code', 50);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys (non-self-referencing)
            $table->foreign('company_id')
                ->references('id')->on('auth.companies')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('created_by_user_id')
                ->references('id')->on('auth.users')
                ->nullOnDelete()->cascadeOnUpdate();

            // Indexes
            $table->index('company_id');
            $table->index('parent_id');
            $table->index(['company_id', 'is_active']);
        });

        // Self-referencing FK must be added after table creation
        Schema::table('inv.item_categories', function (Blueprint $table) {
            $table->foreign('parent_id')
                ->references('id')->on('inv.item_categories')
                ->nullOnDelete()->cascadeOnUpdate();
        });

        // Unique constraint with soft delete awareness
        DB::statement('CREATE UNIQUE INDEX item_categories_company_code_unique
            ON inv.item_categories (company_id, code)
            WHERE deleted_at IS NULL');

        // ─────────────────────────────────────────────────────────────────────
        // inv.items - Products and services master
        // ─────────────────────────────────────────────────────────────────────
        Schema::create('inv.items', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('category_id')->nullable();
            $table->string('sku', 100);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('item_type', 30)->default('product'); // product, service, non_inventory, bundle
            $table->string('unit_of_measure', 50)->default('unit');
            $table->boolean('track_inventory')->default(true);
            $table->boolean('is_purchasable')->default(true);
            $table->boolean('is_sellable')->default(true);
            $table->decimal('cost_price', 15, 6)->default(0);
            $table->decimal('selling_price', 15, 6)->default(0);
            $table->char('currency', 3); // FK to public.currencies.code if exists
            $table->uuid('tax_rate_id')->nullable(); // FK to tax.tax_rates.id
            $table->uuid('income_account_id')->nullable(); // FK to acct.accounts.id (revenue)
            $table->uuid('expense_account_id')->nullable(); // FK to acct.accounts.id (COGS)
            $table->uuid('asset_account_id')->nullable(); // FK to acct.accounts.id (inventory asset)
            $table->decimal('reorder_point', 18, 3)->default(0);
            $table->decimal('reorder_quantity', 18, 3)->default(0);
            $table->decimal('weight', 10, 3)->nullable();
            $table->string('weight_unit', 10)->nullable(); // kg, lb, g, oz
            $table->jsonb('dimensions')->nullable(); // {length, width, height, unit}
            $table->string('barcode', 100)->nullable();
            $table->string('manufacturer', 255)->nullable();
            $table->string('brand', 255)->nullable();
            $table->string('image_url', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('company_id')
                ->references('id')->on('auth.companies')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('category_id')
                ->references('id')->on('inv.item_categories')
                ->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('created_by_user_id')
                ->references('id')->on('auth.users')
                ->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('updated_by_user_id')
                ->references('id')->on('auth.users')
                ->nullOnDelete()->cascadeOnUpdate();

            // Account FKs - added conditionally if acct.accounts exists
            // These are managed via application layer if table doesn't exist

            // Indexes
            $table->index('company_id');
            $table->index('category_id');
            $table->index(['company_id', 'item_type']);
            $table->index(['company_id', 'is_active']);
            $table->index('barcode');
        });

        // Unique SKU per company (soft delete aware)
        DB::statement('CREATE UNIQUE INDEX items_company_sku_unique
            ON inv.items (company_id, sku)
            WHERE deleted_at IS NULL');

        // Check constraint for item_type enum
        DB::statement("ALTER TABLE inv.items ADD CONSTRAINT items_item_type_check
            CHECK (item_type IN ('product', 'service', 'non_inventory', 'bundle'))");

        // Check constraint for weight_unit enum
        DB::statement("ALTER TABLE inv.items ADD CONSTRAINT items_weight_unit_check
            CHECK (weight_unit IS NULL OR weight_unit IN ('kg', 'lb', 'g', 'oz'))");

        // ─────────────────────────────────────────────────────────────────────
        // inv.warehouses - Storage locations
        // ─────────────────────────────────────────────────────────────────────
        Schema::create('inv.warehouses', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->string('code', 50);
            $table->string('name', 255);
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->char('country_code', 2)->nullable(); // FK to public.countries.code if exists
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('company_id')
                ->references('id')->on('auth.companies')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('created_by_user_id')
                ->references('id')->on('auth.users')
                ->nullOnDelete()->cascadeOnUpdate();

            // Indexes
            $table->index('company_id');
            $table->index(['company_id', 'is_active']);
            $table->index(['company_id', 'is_primary']);
        });

        // Unique code per company (soft delete aware)
        DB::statement('CREATE UNIQUE INDEX warehouses_company_code_unique
            ON inv.warehouses (company_id, code)
            WHERE deleted_at IS NULL');

        // ─────────────────────────────────────────────────────────────────────
        // inv.stock_levels - Current quantity per item per warehouse
        // ─────────────────────────────────────────────────────────────────────
        Schema::create('inv.stock_levels', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('warehouse_id');
            $table->uuid('item_id');
            $table->decimal('quantity', 18, 3)->default(0);
            $table->decimal('reserved_quantity', 18, 3)->default(0);
            // available_quantity is a generated column - added via raw SQL
            $table->decimal('reorder_point', 18, 3)->nullable(); // override item default
            $table->decimal('max_stock', 18, 3)->nullable();
            $table->string('bin_location', 50)->nullable();
            $table->date('last_count_date')->nullable();
            $table->decimal('last_count_quantity', 18, 3)->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('company_id')
                ->references('id')->on('auth.companies')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('warehouse_id')
                ->references('id')->on('inv.warehouses')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('item_id')
                ->references('id')->on('inv.items')
                ->cascadeOnDelete()->cascadeOnUpdate();

            // Unique constraint
            $table->unique(['company_id', 'warehouse_id', 'item_id']);

            // Indexes
            $table->index('company_id');
            $table->index('warehouse_id');
            $table->index('item_id');
        });

        // Add generated column for available_quantity
        DB::statement('ALTER TABLE inv.stock_levels
            ADD COLUMN available_quantity numeric(18,3) GENERATED ALWAYS AS (quantity - reserved_quantity) STORED');

        // Index for low stock alerts
        DB::statement('CREATE INDEX stock_levels_low_stock_idx
            ON inv.stock_levels (company_id, quantity)
            WHERE quantity < reorder_point');

        // ─────────────────────────────────────────────────────────────────────
        // inv.stock_movements - Immutable log of all inventory changes
        // ─────────────────────────────────────────────────────────────────────
        Schema::create('inv.stock_movements', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('warehouse_id');
            $table->uuid('item_id');
            $table->date('movement_date')->default(DB::raw('CURRENT_DATE'));
            $table->string('movement_type', 30); // purchase, sale, adjustment_in/out, transfer_in/out, return_in/out, opening
            $table->decimal('quantity', 18, 3); // positive for in, negative for out
            $table->decimal('unit_cost', 15, 6)->nullable();
            $table->decimal('total_cost', 15, 2)->nullable();
            $table->string('reference_type', 100)->nullable(); // e.g., 'acct.bills', 'acct.invoices'
            $table->uuid('reference_id')->nullable();
            $table->uuid('related_movement_id')->nullable(); // for transfers
            $table->string('reason', 255)->nullable();
            $table->text('notes')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamp('created_at')->default(DB::raw('NOW()'));

            // Foreign keys (non-self-referencing)
            $table->foreign('company_id')
                ->references('id')->on('auth.companies')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('warehouse_id')
                ->references('id')->on('inv.warehouses')
                ->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('item_id')
                ->references('id')->on('inv.items')
                ->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('created_by_user_id')
                ->references('id')->on('auth.users')
                ->nullOnDelete()->cascadeOnUpdate();

            // Indexes
            $table->index('company_id');
            $table->index('warehouse_id');
            $table->index('item_id');
            $table->index(['company_id', 'movement_date']);
            $table->index(['reference_type', 'reference_id']);
        });

        // Self-referencing FK must be added after table creation
        Schema::table('inv.stock_movements', function (Blueprint $table) {
            $table->foreign('related_movement_id')
                ->references('id')->on('inv.stock_movements')
                ->nullOnDelete()->cascadeOnUpdate();
        });

        // Check constraint for movement_type enum
        DB::statement("ALTER TABLE inv.stock_movements ADD CONSTRAINT stock_movements_type_check
            CHECK (movement_type IN ('purchase', 'sale', 'adjustment_in', 'adjustment_out',
                'transfer_in', 'transfer_out', 'return_in', 'return_out', 'opening'))");

        // ─────────────────────────────────────────────────────────────────────
        // inv.cost_policies - Costing method configuration per company
        // ─────────────────────────────────────────────────────────────────────
        Schema::create('inv.cost_policies', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id')->unique(); // one policy per company
            $table->string('method', 10)->default('WA'); // WA (Weighted Average), FIFO
            $table->date('effective_from')->default(DB::raw('CURRENT_DATE'));
            $table->boolean('allow_negative_stock')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('company_id')
                ->references('id')->on('auth.companies')
                ->cascadeOnDelete()->cascadeOnUpdate();
        });

        // Check constraint for method enum
        DB::statement("ALTER TABLE inv.cost_policies ADD CONSTRAINT cost_policies_method_check
            CHECK (method IN ('WA', 'FIFO'))");

        // ─────────────────────────────────────────────────────────────────────
        // inv.item_costs - Running cost summary per item per warehouse (for WA)
        // ─────────────────────────────────────────────────────────────────────
        Schema::create('inv.item_costs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('item_id');
            $table->uuid('warehouse_id');
            $table->decimal('avg_unit_cost', 15, 6)->default(0);
            $table->decimal('qty_on_hand', 18, 3)->default(0);
            $table->decimal('value_on_hand', 18, 2)->default(0);
            $table->timestamps();

            // Foreign keys
            $table->foreign('company_id')
                ->references('id')->on('auth.companies')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('item_id')
                ->references('id')->on('inv.items')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('warehouse_id')
                ->references('id')->on('inv.warehouses')
                ->cascadeOnDelete()->cascadeOnUpdate();

            // Unique constraint
            $table->unique(['company_id', 'item_id', 'warehouse_id']);

            // Indexes
            $table->index('company_id');
            $table->index('item_id');
        });

        // ─────────────────────────────────────────────────────────────────────
        // inv.cost_layers - Cost layers for FIFO costing
        // ─────────────────────────────────────────────────────────────────────
        Schema::create('inv.cost_layers', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('item_id');
            $table->uuid('warehouse_id');
            $table->string('source_type', 30); // AP_BILL, ADJUSTMENT, TRANSFER_IN, OPENING
            $table->uuid('source_id')->nullable();
            $table->date('layer_date');
            $table->decimal('original_qty', 18, 3);
            $table->decimal('qty_remaining', 18, 3);
            $table->decimal('unit_cost', 15, 6);
            // total_cost is a generated column - added via raw SQL
            $table->timestamp('created_at')->default(DB::raw('NOW()'));

            // Foreign keys
            $table->foreign('company_id')
                ->references('id')->on('auth.companies')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('item_id')
                ->references('id')->on('inv.items')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('warehouse_id')
                ->references('id')->on('inv.warehouses')
                ->cascadeOnDelete()->cascadeOnUpdate();

            // Indexes
            $table->index(['company_id', 'item_id', 'warehouse_id', 'layer_date'], 'cost_layers_fifo_idx');
            $table->index('item_id');
        });

        // Add generated column for total_cost
        DB::statement('ALTER TABLE inv.cost_layers
            ADD COLUMN total_cost numeric(18,2) GENERATED ALWAYS AS (qty_remaining * unit_cost) STORED');

        // Check constraints
        DB::statement('ALTER TABLE inv.cost_layers ADD CONSTRAINT cost_layers_qty_remaining_check
            CHECK (qty_remaining >= 0)');
        DB::statement('ALTER TABLE inv.cost_layers ADD CONSTRAINT cost_layers_unit_cost_check
            CHECK (unit_cost >= 0)');
        DB::statement("ALTER TABLE inv.cost_layers ADD CONSTRAINT cost_layers_source_type_check
            CHECK (source_type IN ('AP_BILL', 'ADJUSTMENT', 'TRANSFER_IN', 'OPENING'))");

        // ─────────────────────────────────────────────────────────────────────
        // inv.cogs_entries - COGS records at time of sale
        // ─────────────────────────────────────────────────────────────────────
        Schema::create('inv.cogs_entries', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('movement_id')->unique(); // one COGS entry per movement
            $table->uuid('item_id');
            $table->uuid('warehouse_id');
            $table->decimal('qty_issued', 18, 3);
            $table->decimal('unit_cost', 15, 6);
            $table->decimal('cost_amount', 18, 2);
            $table->uuid('gl_transaction_id')->nullable(); // FK to acct.transactions if exists
            $table->timestamp('created_at')->default(DB::raw('NOW()'));

            // Foreign keys
            $table->foreign('company_id')
                ->references('id')->on('auth.companies')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('movement_id')
                ->references('id')->on('inv.stock_movements')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('item_id')
                ->references('id')->on('inv.items')
                ->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('warehouse_id')
                ->references('id')->on('inv.warehouses')
                ->restrictOnDelete()->cascadeOnUpdate();

            // Indexes
            $table->index('company_id');
            $table->index('item_id');
        });

        // ─────────────────────────────────────────────────────────────────────
        // Enable Row Level Security on all tables
        // ─────────────────────────────────────────────────────────────────────
        $tables = [
            'item_categories',
            'items',
            'warehouses',
            'stock_levels',
            'stock_movements',
            'cost_policies',
            'item_costs',
            'cost_layers',
            'cogs_entries',
        ];

        foreach ($tables as $tableName) {
            DB::statement("ALTER TABLE inv.{$tableName} ENABLE ROW LEVEL SECURITY");

            // Company isolation policy with super-admin override
            DB::statement("
                CREATE POLICY {$tableName}_company_isolation ON inv.{$tableName}
                FOR ALL
                USING (
                    company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid
                    OR current_setting('app.is_super_admin', true)::boolean = true
                )
            ");
        }

        // ─────────────────────────────────────────────────────────────────────
        // Create trigger function to update stock_levels on stock_movements
        // ─────────────────────────────────────────────────────────────────────
        DB::statement("
            CREATE OR REPLACE FUNCTION inv.update_stock_level_on_movement()
            RETURNS TRIGGER AS \$\$
            BEGIN
                -- Upsert stock level
                INSERT INTO inv.stock_levels (company_id, warehouse_id, item_id, quantity, reserved_quantity)
                VALUES (NEW.company_id, NEW.warehouse_id, NEW.item_id, NEW.quantity, 0)
                ON CONFLICT (company_id, warehouse_id, item_id)
                DO UPDATE SET
                    quantity = inv.stock_levels.quantity + NEW.quantity,
                    updated_at = NOW();

                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql
        ");

        DB::statement("
            CREATE TRIGGER trg_stock_movements_update_levels
            AFTER INSERT ON inv.stock_movements
            FOR EACH ROW
            EXECUTE FUNCTION inv.update_stock_level_on_movement()
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop trigger first
        DB::statement('DROP TRIGGER IF EXISTS trg_stock_movements_update_levels ON inv.stock_movements');
        DB::statement('DROP FUNCTION IF EXISTS inv.update_stock_level_on_movement()');

        // Drop tables in reverse dependency order
        Schema::dropIfExists('inv.cogs_entries');
        Schema::dropIfExists('inv.cost_layers');
        Schema::dropIfExists('inv.item_costs');
        Schema::dropIfExists('inv.cost_policies');
        Schema::dropIfExists('inv.stock_movements');
        Schema::dropIfExists('inv.stock_levels');
        Schema::dropIfExists('inv.warehouses');
        Schema::dropIfExists('inv.items');
        Schema::dropIfExists('inv.item_categories');

        // Drop the schema
        DB::statement('DROP SCHEMA IF EXISTS inv CASCADE');
    }
};
