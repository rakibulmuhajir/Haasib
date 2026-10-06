<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * What a supplier payment with no bill picked does (Daily Close "Pay supplier" rows, card-channel
 * settlements, a supplier's credit when its next bill posts): oldest_first pays the supplier's
 * open bills oldest first, as before; keep_as_credit leaves it with the supplier as credit until
 * someone applies it to a bill. Existing table, existing RLS.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE fuel.station_settings ADD COLUMN IF NOT EXISTS supplier_payment_allocation varchar(20) NOT NULL DEFAULT 'oldest_first'");
        DB::statement("ALTER TABLE fuel.station_settings ADD CONSTRAINT station_settings_supplier_payment_allocation_check CHECK (supplier_payment_allocation IN ('oldest_first', 'keep_as_credit'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE fuel.station_settings DROP CONSTRAINT IF EXISTS station_settings_supplier_payment_allocation_check');
        DB::statement('ALTER TABLE fuel.station_settings DROP COLUMN IF EXISTS supplier_payment_allocation');
    }
};
