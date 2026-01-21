<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add dip stick chart functionality to tanks.
 * Each tank can have a dip stick with a conversion chart (stick reading → liters).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─────────────────────────────────────────────────────────────────────
        // fuel.dip_sticks - Dip stick definitions (each tank has one stick)
        // ─────────────────────────────────────────────────────────────────────
        Schema::create('fuel.dip_sticks', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->string('code', 50); // Unique identifier like "T", "II", "W"
            $table->string('name', 255); // Display name
            $table->string('unit', 20)->default('cm'); // Unit of measurement (cm, mm, inches)
            $table->decimal('max_reading', 10, 2)->nullable(); // Maximum reading on the stick
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamps();

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
        });

        // Unique code per company
        DB::statement('CREATE UNIQUE INDEX dip_sticks_company_code_unique
            ON fuel.dip_sticks (company_id, code)');

        // ─────────────────────────────────────────────────────────────────────
        // fuel.dip_chart_entries - Conversion chart entries
        // Each entry maps a stick reading to a liter value
        // ─────────────────────────────────────────────────────────────────────
        Schema::create('fuel.dip_chart_entries', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('dip_stick_id');
            $table->decimal('stick_reading', 10, 2); // Reading on the dip stick
            $table->decimal('liters', 12, 2); // Corresponding volume in liters
            $table->timestamps();

            // Foreign keys
            $table->foreign('dip_stick_id')
                ->references('id')->on('fuel.dip_sticks')
                ->cascadeOnDelete()->cascadeOnUpdate();

            // Indexes for fast lookup
            $table->index('dip_stick_id');
            $table->index(['dip_stick_id', 'stick_reading']);
        });

        // Unique reading per dip stick (can't have duplicate readings)
        DB::statement('CREATE UNIQUE INDEX dip_chart_entries_stick_reading_unique
            ON fuel.dip_chart_entries (dip_stick_id, stick_reading)');

        // ─────────────────────────────────────────────────────────────────────
        // Add dip_stick_id to warehouses (tanks)
        // ─────────────────────────────────────────────────────────────────────
        Schema::table('inv.warehouses', function (Blueprint $table) {
            $table->uuid('dip_stick_id')->nullable()->after('linked_item_id');

            $table->foreign('dip_stick_id')
                ->references('id')->on('fuel.dip_sticks')
                ->nullOnDelete()->cascadeOnUpdate();
        });

        // Index for tank with dip stick lookup
        DB::statement("CREATE INDEX warehouses_dip_stick_idx ON inv.warehouses (dip_stick_id) WHERE dip_stick_id IS NOT NULL");

        // ─────────────────────────────────────────────────────────────────────
        // Update tank_readings to store stick reading as well as liters
        // ─────────────────────────────────────────────────────────────────────
        Schema::table('fuel.tank_readings', function (Blueprint $table) {
            // Add stick reading column (the raw reading from the dip stick)
            $table->decimal('stick_reading', 10, 2)->nullable()->after('tank_id');
        });

        // ─────────────────────────────────────────────────────────────────────
        // RLS Policies
        // ─────────────────────────────────────────────────────────────────────
        DB::statement('ALTER TABLE fuel.dip_sticks ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE fuel.dip_sticks FORCE ROW LEVEL SECURITY');

        // Super-admin bypass policy
        DB::statement("CREATE POLICY dip_sticks_super_admin ON fuel.dip_sticks
            FOR ALL
            USING (
                current_setting('app.current_user_id', true) IS NOT NULL
                AND current_setting('app.current_user_id', true)::text LIKE '00000000-0000-0000-0000-%'
            )
            WITH CHECK (
                current_setting('app.current_user_id', true) IS NOT NULL
                AND current_setting('app.current_user_id', true)::text LIKE '00000000-0000-0000-0000-%'
            )
        ");

        // Company isolation policy
        DB::statement("CREATE POLICY dip_sticks_company_isolation ON fuel.dip_sticks
            FOR ALL
            USING (company_id = current_setting('app.current_company_id', true)::uuid)
            WITH CHECK (company_id = current_setting('app.current_company_id', true)::uuid)
        ");

        // dip_chart_entries inherits from dip_sticks (no company_id column)
        DB::statement('ALTER TABLE fuel.dip_chart_entries ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE fuel.dip_chart_entries FORCE ROW LEVEL SECURITY');

        DB::statement("CREATE POLICY dip_chart_entries_super_admin ON fuel.dip_chart_entries
            FOR ALL
            USING (
                current_setting('app.current_user_id', true) IS NOT NULL
                AND current_setting('app.current_user_id', true)::text LIKE '00000000-0000-0000-0000-%'
            )
            WITH CHECK (
                current_setting('app.current_user_id', true) IS NOT NULL
                AND current_setting('app.current_user_id', true)::text LIKE '00000000-0000-0000-0000-%'
            )
        ");

        DB::statement("CREATE POLICY dip_chart_entries_company_isolation ON fuel.dip_chart_entries
            FOR ALL
            USING (
                dip_stick_id IN (
                    SELECT id FROM fuel.dip_sticks
                    WHERE company_id = current_setting('app.current_company_id', true)::uuid
                )
            )
            WITH CHECK (
                dip_stick_id IN (
                    SELECT id FROM fuel.dip_sticks
                    WHERE company_id = current_setting('app.current_company_id', true)::uuid
                )
            )
        ");

        // ─────────────────────────────────────────────────────────────────────
        // Function to convert stick reading to liters using interpolation
        // ─────────────────────────────────────────────────────────────────────
        DB::statement("
            CREATE OR REPLACE FUNCTION fuel.convert_dip_reading(
                p_dip_stick_id uuid,
                p_stick_reading numeric
            ) RETURNS numeric AS \$\$
            DECLARE
                v_lower_reading numeric;
                v_upper_reading numeric;
                v_lower_liters numeric;
                v_upper_liters numeric;
                v_result numeric;
            BEGIN
                -- Check for exact match first
                SELECT liters INTO v_result
                FROM fuel.dip_chart_entries
                WHERE dip_stick_id = p_dip_stick_id
                AND stick_reading = p_stick_reading;

                IF FOUND THEN
                    RETURN v_result;
                END IF;

                -- Get lower bound
                SELECT stick_reading, liters
                INTO v_lower_reading, v_lower_liters
                FROM fuel.dip_chart_entries
                WHERE dip_stick_id = p_dip_stick_id
                AND stick_reading < p_stick_reading
                ORDER BY stick_reading DESC
                LIMIT 1;

                -- Get upper bound
                SELECT stick_reading, liters
                INTO v_upper_reading, v_upper_liters
                FROM fuel.dip_chart_entries
                WHERE dip_stick_id = p_dip_stick_id
                AND stick_reading > p_stick_reading
                ORDER BY stick_reading ASC
                LIMIT 1;

                -- If we have both bounds, interpolate
                IF v_lower_reading IS NOT NULL AND v_upper_reading IS NOT NULL THEN
                    v_result := v_lower_liters +
                        ((p_stick_reading - v_lower_reading) / (v_upper_reading - v_lower_reading)) *
                        (v_upper_liters - v_lower_liters);
                    RETURN ROUND(v_result, 2);
                END IF;

                -- If only lower bound, extrapolate (not ideal, but better than nothing)
                IF v_lower_reading IS NOT NULL THEN
                    RETURN v_lower_liters;
                END IF;

                -- If only upper bound, extrapolate
                IF v_upper_reading IS NOT NULL THEN
                    RETURN v_upper_liters;
                END IF;

                -- No chart entries found
                RETURN NULL;
            END;
            \$\$ LANGUAGE plpgsql STABLE;
        ");
    }

    public function down(): void
    {
        // Drop function
        DB::statement('DROP FUNCTION IF EXISTS fuel.convert_dip_reading(uuid, numeric)');

        // Remove stick_reading from tank_readings
        Schema::table('fuel.tank_readings', function (Blueprint $table) {
            $table->dropColumn('stick_reading');
        });

        // Remove dip_stick_id from warehouses
        DB::statement('DROP INDEX IF EXISTS inv.warehouses_dip_stick_idx');
        Schema::table('inv.warehouses', function (Blueprint $table) {
            $table->dropForeign(['dip_stick_id']);
            $table->dropColumn('dip_stick_id');
        });

        // Drop tables
        Schema::dropIfExists('fuel.dip_chart_entries');
        Schema::dropIfExists('fuel.dip_sticks');
    }
};
