<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acct.bill_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('bill_id');
            $table->integer('line_number');
            $table->uuid('purchase_order_line_id')->nullable();
            $table->uuid('product_id')->nullable();
            $table->text('description');
            $table->decimal('quantity', 10, 4, true);
            $table->decimal('unit_price', 12, 6, true);
            $table->decimal('discount_percentage', 5, 2, true)->default(0);
            $table->decimal('tax_rate', 5, 3, true)->default(0);
            $table->decimal('line_total', 15, 2, true);
            $table->decimal('tax_amount', 15, 2, true);
            $table->decimal('total_with_tax', 15, 2, true);
            $table->uuid('account_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('bill_id')->references('id')->on('acct.bills')->onDelete('cascade');
            $table->foreign('purchase_order_line_id')->references('id')->on('acct.purchase_order_lines')->onDelete('set null');
            // Note: account_id foreign key will be added when accounts table is created

            // Indexes
            $table->index(['bill_id', 'line_number']);
            $table->index('purchase_order_line_id');
            $table->index('account_id');
        });

        // Row Level Security policies
        DB::statement('
            ALTER TABLE acct.bill_lines ENABLE ROW LEVEL SECURITY;
        ');

        // RLS policy for company isolation (via bill relationship)
        DB::statement("
            CREATE POLICY bill_lines_company_isolation_policy ON acct.bill_lines
            FOR ALL TO app_user
            USING (
                bill_id IN (
                    SELECT id FROM acct.bills 
                    WHERE company_id = current_setting('app.current_company_id')::uuid
                )
            );
        ");

        // RLS policy for authenticated users in the app_user role
        DB::statement("
            CREATE POLICY bill_lines_app_user_policy ON acct.bill_lines
            FOR ALL TO app_user
            USING (
                bill_id IN (
                    SELECT id FROM acct.bills 
                    WHERE company_id = current_setting('app.current_company_id')::uuid
                )
            );
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('acct.bill_lines');
    }
};
