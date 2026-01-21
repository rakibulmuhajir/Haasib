<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Create fuel.station_settings - Universal configuration for any gas station
 *
 * This table stores configurable settings that vary by station:
 * - Fuel vendor (PSO, Shell, Total, Caltex, Attock, etc.)
 * - Payment channels with their bank account mappings
 * - Feature toggles (amanat, partners, dual readings, lubricants)
 * - Default account mappings for GL posting
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─────────────────────────────────────────────────────────────────────
        // fuel.station_settings - One row per company
        // ─────────────────────────────────────────────────────────────────────
        Schema::create('fuel.station_settings', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id')->unique(); // One settings row per company

            // Vendor/Brand (determines fuel card type)
            $table->string('fuel_vendor', 30)->default('parco');
            // parco, pso, shell, total, caltex, attock, hascol, byco, go, other

            // Feature toggles
            $table->boolean('has_partners')->default(true);
            $table->boolean('has_amanat')->default(true);
            $table->boolean('has_lubricant_sales')->default(true);
            $table->boolean('has_investors')->default(false);
            $table->boolean('dual_meter_readings')->default(false); // computerized + manual
            $table->boolean('track_attendant_handovers')->default(false);

            // Payment channels (JSONB array)
            // Each entry: { code, label, type, enabled, bank_account_id?, clearing_account_id? }
            // Types: cash, bank_transfer, card_pos, fuel_card, mobile_wallet
            $table->jsonb('payment_channels')->default('[]');

            // Default accounts for GL posting (populated during onboarding)
            $table->uuid('cash_account_id')->nullable();
            $table->uuid('fuel_sales_account_id')->nullable();
            $table->uuid('fuel_cogs_account_id')->nullable();
            $table->uuid('fuel_inventory_account_id')->nullable();
            $table->uuid('cash_over_short_account_id')->nullable();
            $table->uuid('partner_drawings_account_id')->nullable();
            $table->uuid('employee_advances_account_id')->nullable();

            // Operating bank (for deposits to vendor)
            $table->uuid('operating_bank_account_id')->nullable();

            // Fuel card clearing account (vendor-specific)
            $table->uuid('fuel_card_clearing_account_id')->nullable();

            // Card POS clearing account
            $table->uuid('card_pos_clearing_account_id')->nullable();

            $table->timestamps();

            // Foreign keys
            $table->foreign('company_id')
                ->references('id')->on('auth.companies')
                ->cascadeOnDelete()->cascadeOnUpdate();

            $table->foreign('cash_account_id')
                ->references('id')->on('acct.accounts')
                ->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('fuel_sales_account_id')
                ->references('id')->on('acct.accounts')
                ->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('fuel_cogs_account_id')
                ->references('id')->on('acct.accounts')
                ->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('fuel_inventory_account_id')
                ->references('id')->on('acct.accounts')
                ->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('cash_over_short_account_id')
                ->references('id')->on('acct.accounts')
                ->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('partner_drawings_account_id')
                ->references('id')->on('acct.accounts')
                ->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('employee_advances_account_id')
                ->references('id')->on('acct.accounts')
                ->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('operating_bank_account_id')
                ->references('id')->on('acct.accounts')
                ->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('fuel_card_clearing_account_id')
                ->references('id')->on('acct.accounts')
                ->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('card_pos_clearing_account_id')
                ->references('id')->on('acct.accounts')
                ->nullOnDelete()->cascadeOnUpdate();
        });

        // Check constraint for vendor enum
        DB::statement("ALTER TABLE fuel.station_settings ADD CONSTRAINT station_settings_vendor_check
            CHECK (fuel_vendor IN ('parco', 'pso', 'shell', 'total', 'caltex', 'attock', 'hascol', 'byco', 'go', 'other'))");

        // Enable RLS
        DB::statement('ALTER TABLE fuel.station_settings ENABLE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY station_settings_company_isolation ON fuel.station_settings
            FOR ALL
            USING (
                company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel.station_settings');
    }
};
