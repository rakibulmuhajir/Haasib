<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acct.bills', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('vendor_id');
            $table->string('bill_number')->unique();
            $table->enum('status', ['draft', 'pending_approval', 'approved', 'partial', 'paid', 'overdue', 'cancelled'])->default('draft');
            $table->date('bill_date');
            $table->date('due_date');
            $table->string('currency', 3);
            $table->decimal('exchange_rate', 10, 6, true);
            $table->decimal('subtotal', 15, 2, true);
            $table->decimal('tax_total', 15, 2, true);
            $table->decimal('total_amount', 15, 2, true);
            $table->decimal('amount_paid', 15, 2, true)->default(0);
            $table->decimal('balance_due', 15, 2, true);
            $table->string('vendor_bill_number')->nullable();
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->uuid('purchase_order_id')->nullable();
            $table->uuid('created_by');
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('sent_to_vendor_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
            $table->foreign('vendor_id')->references('id')->on('acct.vendors')->onDelete('cascade');
            $table->foreign('purchase_order_id')->references('id')->on('acct.purchase_orders')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('auth.users')->onDelete('restrict');
            $table->foreign('approved_by')->references('id')->on('auth.users')->onDelete('restrict');

            // Indexes
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'vendor_id']);
            $table->index(['company_id', 'bill_date']);
            $table->index(['company_id', 'due_date']);
            $table->index(['company_id', 'bill_number']);
            $table->index('purchase_order_id');
        });

        // Row Level Security policies
        DB::statement('
            ALTER TABLE acct.bills ENABLE ROW LEVEL SECURITY;
        ');

        // RLS policy for company isolation
        DB::statement("
            CREATE POLICY bills_company_isolation_policy ON acct.bills
            FOR ALL TO app_user
            USING (company_id = current_setting('app.current_company_id')::uuid);
        ");

        // RLS policy for authenticated users in the app_user role
        DB::statement("
            CREATE POLICY bills_app_user_policy ON acct.bills
            FOR ALL TO app_user
            USING (company_id = current_setting('app.current_company_id')::uuid);
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('acct.bills');
    }
};
