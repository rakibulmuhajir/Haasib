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
        Schema::create('acct.purchase_order_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('po_id');
            $table->integer('line_number');
            $table->uuid('product_id')->nullable();
            $table->text('description');
            $table->decimal('quantity', 12, 4)->default(0.0000);
            $table->decimal('unit_price', 15, 6)->default(0.000000);
            $table->decimal('discount_percentage', 5, 2)->default(0.00);
            $table->decimal('tax_rate', 8, 5)->default(0.00000);
            $table->decimal('line_total', 15, 2)->default(0.00);
            $table->decimal('received_quantity', 12, 4)->default(0.0000);
            $table->timestamps();

            // Indexes
            $table->index(['po_id']);
            $table->index(['product_id']);
            $table->unique(['po_id', 'line_number']);
        });

        // Enable RLS
        DB::statement('ALTER TABLE acct.purchase_order_lines ENABLE ROW LEVEL SECURITY');

        // Create RLS policies
        DB::statement("
            CREATE POLICY purchase_order_lines_company_policy ON acct.purchase_order_lines
            FOR ALL
            USING (
                po_id IN (
                    SELECT id FROM acct.purchase_orders 
                    WHERE company_id = current_setting('app.current_company_id', true)::uuid
                )
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
            WITH CHECK (
                po_id IN (
                    SELECT id FROM acct.purchase_orders 
                    WHERE company_id = current_setting('app.current_company_id', true)::uuid
                )
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");

        // Add foreign key constraints after table creation
        DB::statement('
            ALTER TABLE acct.purchase_order_lines
            ADD CONSTRAINT purchase_order_lines_po_id_foreign
            FOREIGN KEY (po_id) REFERENCES acct.purchase_orders(id) ON DELETE CASCADE
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS purchase_order_lines_company_policy ON acct.purchase_order_lines');
        DB::statement('ALTER TABLE acct.purchase_order_lines DISABLE ROW LEVEL SECURITY');
        Schema::dropIfExists('acct.purchase_order_lines');
    }
};
