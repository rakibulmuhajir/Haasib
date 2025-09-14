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
            $table->id('invoice_item_id');
            $table->foreignId('invoice_id')->constrained('invoices', 'invoice_id');
            $table->bigInteger('item_id')->nullable(); // Will be FK to inventory.items when available
            $table->string('description', 255);
            $table->decimal('quantity', 10, 3);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('discount_percentage', 5, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Add check constraints
        DB::statement('ALTER TABLE invoice_items ADD CONSTRAINT chk_quantity_positive CHECK (quantity > 0)');
        DB::statement('ALTER TABLE invoice_items ADD CONSTRAINT chk_unit_price_nonneg CHECK (unit_price >= 0)');
        DB::statement('ALTER TABLE invoice_items ADD CONSTRAINT chk_discount_pct_range CHECK (discount_percentage BETWEEN 0 AND 100)');
        DB::statement('ALTER TABLE invoice_items ADD CONSTRAINT chk_discount_nonneg CHECK (discount_amount >= 0)');
        DB::statement('ALTER TABLE invoice_items ADD CONSTRAINT chk_line_total_nonneg CHECK (line_total >= 0)');

        // Add indexes
        DB::statement('CREATE INDEX idx_invoice_items_invoice ON invoice_items(invoice_id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
