<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Convert item_id to UUID and add FK to items.id
        if (Schema::hasTable('invoice_items')) {
            // Drop any existing FK if present (defensive)
            try { DB::statement('ALTER TABLE invoice_items DROP CONSTRAINT IF EXISTS invoice_items_item_id_foreign'); } catch (Throwable $e) {}

            // Change type to uuid (data will be null in typical cases)
            DB::statement("ALTER TABLE invoice_items ALTER COLUMN item_id TYPE uuid USING NULL");

            // Add FK to items.id
            DB::statement("ALTER TABLE invoice_items ADD CONSTRAINT fk_invoice_items_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE SET NULL");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('invoice_items')) {
            // Drop FK and revert to bigint
            try { DB::statement('ALTER TABLE invoice_items DROP CONSTRAINT IF EXISTS fk_invoice_items_item'); } catch (Throwable $e) {}
            DB::statement('ALTER TABLE invoice_items ALTER COLUMN item_id TYPE bigint USING NULL');
        }
    }
};

