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
        Schema::create('invoice_items', function (Blueprint $table) {
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

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->foreign('invoice_id')->references('invoice_id')->on('invoices')->onDelete('cascade');
            $table->index('invoice_id', 'idx_invoice_items_invoice');
        });

        // Add check constraints
        DB::statement('ALTER TABLE invoice_items ADD CONSTRAINT chk_quantity_positive CHECK (quantity > 0)');
        DB::statement('ALTER TABLE invoice_items ADD CONSTRAINT chk_unit_price_nonneg CHECK (unit_price >= 0)');
        DB::statement('ALTER TABLE invoice_items ADD CONSTRAINT chk_discount_pct_range CHECK (discount_percentage BETWEEN 0 AND 100)');
        DB::statement('ALTER TABLE invoice_items ADD CONSTRAINT chk_discount_nonneg CHECK (discount_amount >= 0)');
        DB::statement('ALTER TABLE invoice_items ADD CONSTRAINT chk_line_total_nonneg CHECK (line_total >= 0)');

        // Idempotency unique scope within invoice
        try {
            DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS invoice_items_idemp_unique ON invoice_items (invoice_id, idempotency_key) WHERE idempotency_key IS NOT NULL");
        } catch (\\Throwable $e) { /* ignore */ }

        // Enable RLS and tenant policy via parent invoice
        DB::statement('ALTER TABLE invoice_items ENABLE ROW LEVEL SECURITY');
        DB::statement(<<<SQL
            CREATE POLICY invoice_items_tenant_isolation ON invoice_items
            USING (EXISTS (
                SELECT 1 FROM invoices i
                WHERE i.invoice_id = invoice_items.invoice_id
                  AND i.company_id = current_setting('app.current_company', true)::uuid
            ))
            WITH CHECK (EXISTS (
                SELECT 1 FROM invoices i
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
        Schema::dropIfExists('invoice_items');
    }
};
