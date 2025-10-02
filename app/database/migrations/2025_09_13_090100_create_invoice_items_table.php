<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('acct.invoice_items', function (Blueprint $table) {
            $table->uuid('invoice_item_id')->primary();
            $table->uuid('invoice_id');
            $table->bigInteger('item_id')->nullable(); // Will be FK to inventory.items when available
            $table->string('description', 255);
            $table->decimal('quantity', 10, 3);
            $table->decimal('unit_price', 15, 2);
            $table->boolean('tax_inclusive')->default(false);
            $table->decimal('discount_percentage', 5, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2);
            $table->integer('sort_order')->default(0);
            // Idempotency: prevent duplicate line inserts on retry
            $table->string('idempotency_key', 128)->nullable();
            $table->timestamps();
        });

        Schema::table('acct.invoice_items', function (Blueprint $table) {
            $table->foreign('invoice_id', 'invoice_items_invoice_fk')
                ->references('invoice_id')
                ->on('acct.invoices')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->index('invoice_id', 'idx_invoice_items_invoice');
        });

        // Add check constraints
        DB::statement('ALTER TABLE acct.invoice_items ADD CONSTRAINT chk_quantity_positive CHECK (quantity > 0)');
        DB::statement('ALTER TABLE acct.invoice_items ADD CONSTRAINT chk_unit_price_nonneg CHECK (unit_price >= 0)');
        DB::statement('ALTER TABLE acct.invoice_items ADD CONSTRAINT chk_discount_pct_range CHECK (discount_percentage BETWEEN 0 AND 100)');
        DB::statement('ALTER TABLE acct.invoice_items ADD CONSTRAINT chk_discount_nonneg CHECK (discount_amount >= 0)');
        DB::statement('ALTER TABLE acct.invoice_items ADD CONSTRAINT chk_line_total_nonneg CHECK (line_total >= 0)');

        // Idempotency unique scope within invoice
        try {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS invoice_items_idemp_unique ON acct.invoice_items (invoice_id, idempotency_key) WHERE idempotency_key IS NOT NULL');
        } catch (\Throwable $e) {
            // Ignore driver-specific errors when creating partial indexes.
        }

        // Enable RLS and tenant policy via parent invoice
        DB::statement('ALTER TABLE acct.invoice_items ENABLE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY invoice_items_tenant_isolation ON acct.invoice_items
            USING (EXISTS (
                SELECT 1 FROM acct.invoices i
                WHERE i.invoice_id = invoice_items.invoice_id
                  AND i.company_id = current_setting('app.current_company', true)::uuid
            ))
            WITH CHECK (EXISTS (
                SELECT 1 FROM acct.invoices i
                WHERE i.invoice_id = invoice_items.invoice_id
                  AND i.company_id = current_setting('app.current_company', true)::uuid
            ));
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE IF EXISTS acct.invoice_items DISABLE ROW LEVEL SECURITY');
        DB::statement("DROP POLICY IF EXISTS invoice_items_tenant_isolation ON acct.invoice_items");

        DB::statement('ALTER TABLE IF EXISTS acct.invoice_items DROP CONSTRAINT IF EXISTS chk_quantity_positive');
        DB::statement('ALTER TABLE IF EXISTS acct.invoice_items DROP CONSTRAINT IF EXISTS chk_unit_price_nonneg');
        DB::statement('ALTER TABLE IF EXISTS acct.invoice_items DROP CONSTRAINT IF EXISTS chk_discount_pct_range');
        DB::statement('ALTER TABLE IF EXISTS acct.invoice_items DROP CONSTRAINT IF EXISTS chk_discount_nonneg');
        DB::statement('ALTER TABLE IF EXISTS acct.invoice_items DROP CONSTRAINT IF EXISTS chk_line_total_nonneg');

        try {
            DB::statement('DROP INDEX IF EXISTS acct.invoice_items_idemp_unique');
        } catch (\Throwable $e) {
            // Ignore driver-specific errors when dropping partial indexes.
        }

        if (Schema::hasTable('acct.invoice_items')) {
            Schema::table('acct.invoice_items', function (Blueprint $table) {
                $table->dropForeign('invoice_items_invoice_fk');
                $table->dropIndex('idx_invoice_items_invoice');
            });
        }

        Schema::dropIfExists('acct.invoice_items');
    }
};
