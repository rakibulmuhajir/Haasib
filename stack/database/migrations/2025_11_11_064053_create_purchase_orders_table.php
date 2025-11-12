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
        Schema::create('acct.purchase_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('po_number', 50)->unique();
            $table->uuid('vendor_id');
            $table->enum('status', ['draft', 'pending_approval', 'approved', 'sent', 'partial_received', 'received', 'closed', 'cancelled'])->default('draft');
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->decimal('exchange_rate', 12, 6)->default(1.000000);
            $table->decimal('subtotal', 15, 2)->default(0.00);
            $table->decimal('tax_amount', 15, 2)->default(0.00);
            $table->decimal('total_amount', 15, 2)->default(0.00);
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('sent_to_vendor_at')->nullable();
            $table->uuid('created_by');
            $table->timestamps();

            // Indexes
            $table->index(['company_id']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'order_date']);
            $table->index(['vendor_id']);
            $table->index(['po_number']);
        });

        // Enable RLS
        DB::statement('ALTER TABLE acct.purchase_orders ENABLE ROW LEVEL SECURITY');

        // Create RLS policies
        DB::statement("
            CREATE POLICY purchase_orders_company_policy ON acct.purchase_orders
            FOR ALL
            USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
            WITH CHECK (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");

        // Add foreign key constraints after table creation
        DB::statement('
            ALTER TABLE acct.purchase_orders
            ADD CONSTRAINT purchase_orders_company_id_foreign
            FOREIGN KEY (company_id) REFERENCES auth.companies(id) ON DELETE CASCADE
        ');

        DB::statement('
            ALTER TABLE acct.purchase_orders
            ADD CONSTRAINT purchase_orders_vendor_id_foreign
            FOREIGN KEY (vendor_id) REFERENCES acct.vendors(id) ON DELETE RESTRICT
        ');

        DB::statement('
            ALTER TABLE acct.purchase_orders
            ADD CONSTRAINT purchase_orders_approved_by_foreign
            FOREIGN KEY (approved_by) REFERENCES auth.users(id) ON DELETE SET NULL
        ');

        DB::statement('
            ALTER TABLE acct.purchase_orders
            ADD CONSTRAINT purchase_orders_created_by_foreign
            FOREIGN KEY (created_by) REFERENCES auth.users(id) ON DELETE RESTRICT
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS purchase_orders_company_policy ON acct.purchase_orders');
        DB::statement('ALTER TABLE acct.purchase_orders DISABLE ROW LEVEL SECURITY');
        Schema::dropIfExists('acct.purchase_orders');
    }
};
