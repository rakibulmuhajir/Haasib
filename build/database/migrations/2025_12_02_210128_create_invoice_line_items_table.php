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
        if (Schema::hasTable('acct.invoice_line_items')) {
            return;
        }

        Schema::create('acct.invoice_line_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('invoice_id');
            $table->integer('line_number');
            $table->string('description', 500);
            $table->decimal('quantity', 10, 2)->default(1.00);
            $table->decimal('unit_price', 15, 2)->default(0.00);
            $table->decimal('tax_rate', 5, 2)->default(0.00);
            $table->decimal('discount_rate', 5, 2)->default(0.00);
            $table->decimal('line_total', 15, 2)->default(0.00);
            $table->decimal('tax_amount', 15, 2)->default(0.00);
            $table->decimal('total', 15, 2)->default(0.00);
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete();
            $table->foreign('invoice_id')->references('id')->on('acct.invoices')->cascadeOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->nullOnDelete();
            $table->foreign('updated_by_user_id')->references('id')->on('auth.users')->nullOnDelete();

            // Indexes
            $table->index(['company_id']);
            $table->index(['invoice_id', 'line_number']);
            $table->index(['invoice_id']);
            $table->index(['company_id', 'invoice_id']);

            // Unique constraint: line numbers must be unique per invoice
            $table->unique(['invoice_id', 'line_number'], 'unique_invoice_line_number');
        });

        // Enable RLS
        DB::statement('ALTER TABLE acct.invoice_line_items ENABLE ROW LEVEL SECURITY');

        // Create RLS policies
        DB::statement("
            CREATE POLICY invoice_line_items_company_policy ON acct.invoice_line_items
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

        // Add constraints
        DB::statement('
            ALTER TABLE acct.invoice_line_items
            ADD CONSTRAINT invoice_line_items_positive_values
            CHECK (
                quantity > 0
                AND unit_price >= 0
                AND tax_rate >= 0
                AND discount_rate >= 0
                AND line_total >= 0
                AND tax_amount >= 0
                AND total >= 0
                AND line_number > 0
            )
        ');

        DB::statement('
            ALTER TABLE acct.invoice_line_items
            ADD CONSTRAINT invoice_line_items_discount_max
            CHECK (discount_rate <= 100)
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS invoice_line_items_company_policy ON acct.invoice_line_items');
        DB::statement('ALTER TABLE acct.invoice_line_items DISABLE ROW LEVEL SECURITY');
        Schema::dropIfExists('acct.invoice_line_items');
    }
};
