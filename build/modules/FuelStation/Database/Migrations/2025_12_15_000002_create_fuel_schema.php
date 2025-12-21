<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Create the fuel schema and all fuel station specific tables:
 * - fuel.pumps: Dispensing machines with meter tracking
 * - fuel.rate_changes: Government-mandated price changes (source of truth for rates)
 * - fuel.tank_readings: Manual dip measurements for variance calculation
 * - fuel.pump_readings: Meter counter readings per pump per shift
 * - fuel.investors: Investor master record
 * - fuel.investor_lots: Lot model for investment tracking
 * - fuel.amanat_transactions: Trust deposit movements
 * - fuel.attendant_handovers: Cash transit from attendants to company
 */
return new class extends Migration
{
    public function up(): void
    {
        // Create the fuel schema
        DB::statement('CREATE SCHEMA IF NOT EXISTS fuel');

        // ─────────────────────────────────────────────────────────────────────
        // fuel.pumps - Dispensing machines with meter tracking
        // ─────────────────────────────────────────────────────────────────────
        Schema::create('fuel.pumps', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->string('name', 100);
            $table->uuid('tank_id'); // FK to inv.warehouses (linked tank)
            $table->decimal('current_meter_reading', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('company_id')
                ->references('id')->on('auth.companies')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('tank_id')
                ->references('id')->on('inv.warehouses')
                ->restrictOnDelete()->cascadeOnUpdate();

            // Indexes
            $table->index('company_id');
            $table->index('tank_id');
            $table->index(['company_id', 'is_active']);
        });

        // Add pump_id FK to invoices now that fuel.pumps exists
        DB::statement('ALTER TABLE acct.invoices
            ADD CONSTRAINT invoices_pump_id_fk FOREIGN KEY (pump_id)
            REFERENCES fuel.pumps(id) ON DELETE SET NULL ON UPDATE CASCADE');

        // ─────────────────────────────────────────────────────────────────────
        // fuel.rate_changes - Government-mandated price changes
        // SOURCE OF TRUTH for current rates (not stored on items)
        // ─────────────────────────────────────────────────────────────────────
        Schema::create('fuel.rate_changes', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('item_id'); // FK to inv.items (fuel item)
            $table->date('effective_date');
            $table->decimal('purchase_rate', 10, 2); // new purchase rate per liter
            $table->decimal('sale_rate', 10, 2); // new sale rate per liter
            $table->decimal('stock_quantity_at_change', 12, 2)->nullable(); // snapshot for margin impact calc
            $table->decimal('margin_impact', 12, 2)->nullable(); // calculated: (new_margin - old_margin) * stock
            $table->text('notes')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('company_id')
                ->references('id')->on('auth.companies')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('item_id')
                ->references('id')->on('inv.items')
                ->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('created_by_user_id')
                ->references('id')->on('auth.users')
                ->nullOnDelete()->cascadeOnUpdate();

            // Indexes
            $table->index('company_id');
            $table->index('item_id');
            $table->index(['company_id', 'item_id', 'effective_date']);
        });

        // Unique constraint: one rate per item per effective_date
        DB::statement('CREATE UNIQUE INDEX rate_changes_item_date_unique
            ON fuel.rate_changes (company_id, item_id, effective_date)');

        // ─────────────────────────────────────────────────────────────────────
        // fuel.tank_readings - Manual dip measurements for variance calculation
        // Variance JE only created from here (with posting workflow)
        // ─────────────────────────────────────────────────────────────────────
        Schema::create('fuel.tank_readings', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('tank_id'); // FK to inv.warehouses
            $table->uuid('item_id'); // DERIVED from tank→linked_item_id, stored for integrity
            $table->dateTime('reading_date');
            $table->string('reading_type', 20); // opening, closing, spot_check
            $table->decimal('dip_measurement_liters', 12, 2);
            $table->decimal('system_calculated_liters', 12, 2); // from sales/purchases
            $table->decimal('variance_liters', 10, 2)->nullable();
            $table->string('variance_type', 10)->nullable(); // loss, gain, none
            $table->string('variance_reason', 30)->nullable(); // evaporation, leak_suspected, etc.
            $table->string('status', 20)->default('draft'); // draft, confirmed, posted
            $table->uuid('journal_entry_id')->nullable(); // FK to acct.journal_entries
            $table->uuid('recorded_by_user_id')->nullable();
            $table->uuid('confirmed_by_user_id')->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('company_id')
                ->references('id')->on('auth.companies')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('tank_id')
                ->references('id')->on('inv.warehouses')
                ->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('item_id')
                ->references('id')->on('inv.items')
                ->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('recorded_by_user_id')
                ->references('id')->on('auth.users')
                ->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('confirmed_by_user_id')
                ->references('id')->on('auth.users')
                ->nullOnDelete()->cascadeOnUpdate();

            // Indexes
            $table->index('company_id');
            $table->index('tank_id');
            $table->index('item_id');
            $table->index(['company_id', 'reading_date']);
            $table->index(['company_id', 'status']);
        });

        // Check constraints for enums
        DB::statement("ALTER TABLE fuel.tank_readings ADD CONSTRAINT tank_readings_type_check
            CHECK (reading_type IN ('opening', 'closing', 'spot_check'))");
        DB::statement("ALTER TABLE fuel.tank_readings ADD CONSTRAINT tank_readings_variance_type_check
            CHECK (variance_type IS NULL OR variance_type IN ('loss', 'gain', 'none'))");
        DB::statement("ALTER TABLE fuel.tank_readings ADD CONSTRAINT tank_readings_variance_reason_check
            CHECK (variance_reason IS NULL OR variance_reason IN
                ('evaporation', 'leak_suspected', 'meter_fault', 'dip_error', 'temperature', 'theft_suspected', 'unknown'))");
        DB::statement("ALTER TABLE fuel.tank_readings ADD CONSTRAINT tank_readings_status_check
            CHECK (status IN ('draft', 'confirmed', 'posted'))");

        // ─────────────────────────────────────────────────────────────────────
        // fuel.pump_readings - Meter counter readings per pump per shift
        // ─────────────────────────────────────────────────────────────────────
        Schema::create('fuel.pump_readings', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('pump_id');
            $table->uuid('item_id'); // DERIVED from pump→tank→linked_item_id, stored for integrity
            $table->date('reading_date');
            $table->string('shift', 20); // day, night
            $table->decimal('opening_meter', 12, 2);
            $table->decimal('closing_meter', 12, 2);
            $table->decimal('liters_dispensed', 12, 2)->nullable(); // calculated: closing - opening
            $table->uuid('recorded_by_user_id')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('company_id')
                ->references('id')->on('auth.companies')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('pump_id')
                ->references('id')->on('fuel.pumps')
                ->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('item_id')
                ->references('id')->on('inv.items')
                ->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('recorded_by_user_id')
                ->references('id')->on('auth.users')
                ->nullOnDelete()->cascadeOnUpdate();

            // Indexes
            $table->index('company_id');
            $table->index('pump_id');
            $table->index('item_id');
            $table->index(['company_id', 'reading_date']);
        });

        // Unique constraint: one reading per pump per date per shift
        DB::statement('CREATE UNIQUE INDEX pump_readings_pump_date_shift_unique
            ON fuel.pump_readings (company_id, pump_id, reading_date, shift)');

        // Check constraints
        DB::statement("ALTER TABLE fuel.pump_readings ADD CONSTRAINT pump_readings_shift_check
            CHECK (shift IN ('day', 'night'))");

        // ─────────────────────────────────────────────────────────────────────
        // fuel.investors - Investor master record
        // ─────────────────────────────────────────────────────────────────────
        Schema::create('fuel.investors', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->string('name', 255);
            $table->string('phone', 20)->nullable();
            $table->string('cnic', 20)->nullable(); // Pakistani national ID
            $table->decimal('total_invested', 15, 2)->default(0); // sum of all lots
            $table->decimal('total_commission_earned', 15, 2)->default(0);
            $table->decimal('total_commission_paid', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->uuid('investor_account_id')->nullable(); // FK to acct.accounts (sub-ledger)
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('company_id')
                ->references('id')->on('auth.companies')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('investor_account_id')
                ->references('id')->on('acct.accounts')
                ->nullOnDelete()->cascadeOnUpdate();

            // Indexes
            $table->index('company_id');
            $table->index(['company_id', 'is_active']);
        });

        // ─────────────────────────────────────────────────────────────────────
        // fuel.investor_lots - Lot model for investment tracking
        // Locked entitlement_rate prevents rate-change disputes
        // ─────────────────────────────────────────────────────────────────────
        Schema::create('fuel.investor_lots', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('investor_id');
            $table->date('deposit_date');
            $table->decimal('investment_amount', 15, 2);
            $table->decimal('entitlement_rate', 10, 2); // LOCKED at deposit time (purchase rate)
            $table->decimal('commission_rate', 5, 2); // LOCKED at deposit (typically 2 PKR/liter)
            $table->decimal('units_entitled', 12, 2); // investment / entitlement_rate (fixed)
            $table->decimal('units_remaining', 12, 2); // decrements as sales occur
            $table->decimal('commission_earned', 15, 2)->default(0);
            $table->string('status', 20)->default('active'); // active, depleted, withdrawn
            $table->uuid('journal_entry_id')->nullable(); // deposit JE
            $table->timestamps();

            // Foreign keys
            $table->foreign('company_id')
                ->references('id')->on('auth.companies')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('investor_id')
                ->references('id')->on('fuel.investors')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('journal_entry_id')
                ->references('id')->on('acct.journal_entries')
                ->nullOnDelete()->cascadeOnUpdate();

            // Indexes
            $table->index('company_id');
            $table->index('investor_id');
            $table->index(['company_id', 'status']);
        });

        // Check constraint for status enum
        DB::statement("ALTER TABLE fuel.investor_lots ADD CONSTRAINT investor_lots_status_check
            CHECK (status IN ('active', 'depleted', 'withdrawn'))");

        // ─────────────────────────────────────────────────────────────────────
        // fuel.amanat_transactions - Trust deposit movements
        // ─────────────────────────────────────────────────────────────────────
        Schema::create('fuel.amanat_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('customer_id'); // FK to acct.customers (where is_amanat_holder=true)
            $table->string('transaction_type', 20); // deposit, withdrawal, fuel_purchase
            $table->decimal('amount', 15, 2);
            $table->uuid('fuel_item_id')->nullable(); // FK to inv.items if fuel_purchase
            $table->decimal('fuel_quantity', 10, 2)->nullable();
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->uuid('recorded_by_user_id')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('company_id')
                ->references('id')->on('auth.companies')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('customer_id')
                ->references('id')->on('acct.customers')
                ->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('fuel_item_id')
                ->references('id')->on('inv.items')
                ->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('recorded_by_user_id')
                ->references('id')->on('auth.users')
                ->nullOnDelete()->cascadeOnUpdate();

            // Indexes
            $table->index('company_id');
            $table->index('customer_id');
            $table->index(['company_id', 'transaction_type']);
        });

        // Check constraint for transaction_type enum
        DB::statement("ALTER TABLE fuel.amanat_transactions ADD CONSTRAINT amanat_transactions_type_check
            CHECK (transaction_type IN ('deposit', 'withdrawal', 'fuel_purchase'))");

        // ─────────────────────────────────────────────────────────────────────
        // fuel.attendant_handovers - Cash transit from attendants to company
        // Control surface for fraud/mistakes
        // ─────────────────────────────────────────────────────────────────────
        Schema::create('fuel.attendant_handovers', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('attendant_id'); // FK to auth.users (who collected)
            $table->dateTime('handover_date');
            $table->uuid('pump_id');
            $table->string('shift', 20);

            // Channel breakdown (even if some are zero)
            $table->decimal('cash_amount', 15, 2)->default(0);
            $table->decimal('easypaisa_amount', 15, 2)->default(0);
            $table->decimal('jazzcash_amount', 15, 2)->default(0);
            $table->decimal('bank_transfer_amount', 15, 2)->default(0);
            $table->decimal('card_swipe_amount', 15, 2)->default(0);
            $table->decimal('parco_card_amount', 15, 2)->default(0); // goes to clearing, not bank

            $table->decimal('total_amount', 15, 2); // computed sum
            $table->uuid('destination_bank_id')->nullable(); // FK to acct.accounts
            $table->string('status', 20)->default('pending'); // pending, received, reconciled
            $table->uuid('received_by_user_id')->nullable();
            $table->dateTime('received_at')->nullable();
            $table->uuid('journal_entry_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('company_id')
                ->references('id')->on('auth.companies')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('attendant_id')
                ->references('id')->on('auth.users')
                ->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('pump_id')
                ->references('id')->on('fuel.pumps')
                ->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('destination_bank_id')
                ->references('id')->on('acct.accounts')
                ->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('received_by_user_id')
                ->references('id')->on('auth.users')
                ->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('journal_entry_id')
                ->references('id')->on('acct.journal_entries')
                ->nullOnDelete()->cascadeOnUpdate();

            // Indexes
            $table->index('company_id');
            $table->index('attendant_id');
            $table->index('pump_id');
            $table->index(['company_id', 'handover_date']);
            $table->index(['company_id', 'status']);
        });

        // Check constraints
        DB::statement("ALTER TABLE fuel.attendant_handovers ADD CONSTRAINT attendant_handovers_shift_check
            CHECK (shift IN ('day', 'night'))");
        DB::statement("ALTER TABLE fuel.attendant_handovers ADD CONSTRAINT attendant_handovers_status_check
            CHECK (status IN ('pending', 'received', 'reconciled'))");

        // ─────────────────────────────────────────────────────────────────────
        // Enable Row Level Security on all fuel tables
        // ─────────────────────────────────────────────────────────────────────
        $tables = [
            'pumps',
            'rate_changes',
            'tank_readings',
            'pump_readings',
            'investors',
            'investor_lots',
            'amanat_transactions',
            'attendant_handovers',
        ];

        foreach ($tables as $tableName) {
            DB::statement("ALTER TABLE fuel.{$tableName} ENABLE ROW LEVEL SECURITY");

            // Company isolation policy with super-admin override
            DB::statement("
                CREATE POLICY {$tableName}_company_isolation ON fuel.{$tableName}
                FOR ALL
                USING (
                    company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid
                    OR current_setting('app.is_super_admin', true)::boolean = true
                )
            ");
        }
    }

    public function down(): void
    {
        // Drop FK from invoices first
        DB::statement('ALTER TABLE acct.invoices DROP CONSTRAINT IF EXISTS invoices_pump_id_fk');

        // Drop tables in reverse dependency order
        Schema::dropIfExists('fuel.attendant_handovers');
        Schema::dropIfExists('fuel.amanat_transactions');
        Schema::dropIfExists('fuel.investor_lots');
        Schema::dropIfExists('fuel.investors');
        Schema::dropIfExists('fuel.pump_readings');
        Schema::dropIfExists('fuel.tank_readings');
        Schema::dropIfExists('fuel.rate_changes');
        Schema::dropIfExists('fuel.pumps');

        // Drop the schema
        DB::statement('DROP SCHEMA IF EXISTS fuel CASCADE');
    }
};
