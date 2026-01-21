<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Create fuel.nozzles table.
 *
 * Each pump can have multiple nozzles (typically 2 - one per side).
 * Each nozzle dispenses a specific fuel type and has its own meter reading.
 *
 * Example: Pump 1 has 2 nozzles:
 *   - Nozzle 1A: Diesel (from Tank 1)
 *   - Nozzle 1B: Diesel (from Tank 1)
 *
 * Example: Pump 2 has 2 nozzles:
 *   - Nozzle 2A: Petrol (from Tank 2)
 *   - Nozzle 2B: Hi-Octane (from Tank 3)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─────────────────────────────────────────────────────────────────────
        // fuel.nozzles - Individual dispensing points on pumps
        // ─────────────────────────────────────────────────────────────────────
        Schema::create('fuel.nozzles', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('pump_id');
            $table->uuid('tank_id'); // Which tank this nozzle draws from
            $table->uuid('item_id'); // Which fuel item (derived from tank, stored for quick access)

            $table->string('code', 20); // e.g., "1A", "1B", "2A", "2B"
            $table->string('label', 100)->nullable(); // e.g., "Pump 1 - Left", "Pump 1 - Right"

            // Meter readings
            $table->decimal('current_meter_reading', 12, 2)->default(0);
            $table->decimal('last_closing_reading', 12, 2)->default(0);

            // For dual reading verification (computerized vs manual)
            $table->boolean('has_electronic_meter')->default(true);

            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('company_id')
                ->references('id')->on('auth.companies')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('pump_id')
                ->references('id')->on('fuel.pumps')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('tank_id')
                ->references('id')->on('inv.warehouses')
                ->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('item_id')
                ->references('id')->on('inv.items')
                ->restrictOnDelete()->cascadeOnUpdate();

            // Indexes
            $table->index('company_id');
            $table->index('pump_id');
            $table->index('tank_id');
            $table->index('item_id');
            $table->index(['company_id', 'is_active']);
        });

        // Unique code per company
        DB::statement('CREATE UNIQUE INDEX nozzles_company_code_unique
            ON fuel.nozzles (company_id, code) WHERE deleted_at IS NULL');

        // ─────────────────────────────────────────────────────────────────────
        // fuel.nozzle_readings - Daily readings per nozzle
        // Replaces pump_readings with more granular nozzle-level tracking
        // ─────────────────────────────────────────────────────────────────────
        Schema::create('fuel.nozzle_readings', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('nozzle_id');
            $table->uuid('item_id'); // Fuel type (from nozzle, stored for integrity)
            $table->date('reading_date');

            // Electronic/computerized reading (official)
            $table->decimal('opening_electronic', 12, 2);
            $table->decimal('closing_electronic', 12, 2);

            // Manual meter reading (verification)
            $table->decimal('opening_manual', 12, 2)->nullable();
            $table->decimal('closing_manual', 12, 2)->nullable();

            // Calculated values
            $table->decimal('liters_dispensed', 12, 2)->nullable(); // closing - opening
            $table->decimal('electronic_manual_variance', 10, 2)->nullable(); // difference if both readings exist

            $table->uuid('recorded_by_user_id')->nullable();
            $table->uuid('daily_close_transaction_id')->nullable(); // Link to daily close transaction
            $table->timestamps();

            // Foreign keys
            $table->foreign('company_id')
                ->references('id')->on('auth.companies')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('nozzle_id')
                ->references('id')->on('fuel.nozzles')
                ->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('item_id')
                ->references('id')->on('inv.items')
                ->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('recorded_by_user_id')
                ->references('id')->on('auth.users')
                ->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('daily_close_transaction_id')
                ->references('id')->on('acct.transactions')
                ->nullOnDelete()->cascadeOnUpdate();

            // Indexes
            $table->index('company_id');
            $table->index('nozzle_id');
            $table->index('item_id');
            $table->index(['company_id', 'reading_date']);
        });

        // Unique: one reading per nozzle per date
        DB::statement('CREATE UNIQUE INDEX nozzle_readings_nozzle_date_unique
            ON fuel.nozzle_readings (company_id, nozzle_id, reading_date)');

        // ─────────────────────────────────────────────────────────────────────
        // Enable RLS
        // ─────────────────────────────────────────────────────────────────────
        DB::statement('ALTER TABLE fuel.nozzles ENABLE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY nozzles_company_isolation ON fuel.nozzles
            FOR ALL
            USING (
                company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");

        DB::statement('ALTER TABLE fuel.nozzle_readings ENABLE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY nozzle_readings_company_isolation ON fuel.nozzle_readings
            FOR ALL
            USING (
                company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel.nozzle_readings');
        Schema::dropIfExists('fuel.nozzles');
    }
};
