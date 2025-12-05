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
        if (Schema::hasTable('acct.invoices')) {
            return;
        }

        DB::statement('CREATE SCHEMA IF NOT EXISTS acct');

        Schema::create('acct.invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('customer_id');
            $table->string('invoice_number', 50);
            $table->date('invoice_date')->default(DB::raw('current_date'));
            $table->date('due_date');
            $table->string('status', 20)->default('draft');
            $table->char('currency', 3)->default('USD');
            $table->char('base_currency', 3);
            $table->decimal('exchange_rate', 18, 8)->nullable();
            $table->decimal('subtotal', 18, 6)->default(0.00);
            $table->decimal('tax_amount', 18, 6)->default(0.00);
            $table->decimal('discount_amount', 18, 6)->default(0.00);
            $table->decimal('total_amount', 18, 6)->default(0.00);
            $table->decimal('paid_amount', 18, 6)->default(0.00);
            $table->decimal('balance', 18, 6)->default(0.00);
            $table->decimal('base_amount', 15, 2)->default(0.00);
            $table->integer('payment_terms')->default(30);
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->uuid('recurring_schedule_id')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete();
            $table->foreign('customer_id')->references('id')->on('acct.customers')->restrictOnDelete();
            $table->foreign('recurring_schedule_id')->references('id')->on('acct.recurring_schedules')->nullOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->nullOnDelete();
            $table->foreign('updated_by_user_id')->references('id')->on('auth.users')->nullOnDelete();

            $table->index('company_id');
            $table->index('customer_id');
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'invoice_date', 'due_date']);
            $table->index(['company_id', 'due_date', 'status']);
        });

        // Soft-delete aware unique invoice number per company
        DB::statement('CREATE UNIQUE INDEX invoices_company_number_unique ON acct.invoices (company_id, invoice_number) WHERE deleted_at IS NULL');

        // Check constraints
        DB::statement("ALTER TABLE acct.invoices ADD CONSTRAINT invoices_currency_format CHECK (currency ~ '^[A-Z]{3}$')");
        DB::statement("ALTER TABLE acct.invoices ADD CONSTRAINT invoices_base_currency_format CHECK (base_currency ~ '^[A-Z]{3}$')");
        DB::statement("ALTER TABLE acct.invoices ADD CONSTRAINT invoices_status_enum CHECK (status IN ('draft','sent','viewed','partial','paid','overdue','void','cancelled'))");
        DB::statement("ALTER TABLE acct.invoices ADD CONSTRAINT invoices_payment_terms_range CHECK (payment_terms >= 0 AND payment_terms <= 365)");
        DB::statement("ALTER TABLE acct.invoices ADD CONSTRAINT invoices_amounts_non_negative CHECK (subtotal >= 0 AND tax_amount >= 0 AND discount_amount >= 0 AND total_amount >= 0 AND paid_amount >= 0 AND balance >= 0 AND base_amount >= 0)");

        // Enable RLS
        DB::statement('ALTER TABLE acct.invoices ENABLE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY invoices_company_policy ON acct.invoices
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS invoices_company_policy ON acct.invoices');
        DB::statement('ALTER TABLE acct.invoices DISABLE ROW LEVEL SECURITY');
        Schema::dropIfExists('acct.invoices');
    }
};
