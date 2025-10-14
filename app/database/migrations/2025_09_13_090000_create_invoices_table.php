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
        Schema::create('acct.invoices', function (Blueprint $table) {
            $table->uuid('invoice_id')->primary();
            $table->uuid('company_id');
            $table->uuid('customer_id')->nullable();
            $table->string('invoice_number', 100);
            $table->string('reference_number', 100)->nullable();
            $table->date('invoice_date');
            $table->date('due_date');
            $table->uuid('currency_id');
            $table->decimal('exchange_rate', 20, 10)->default(1);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('shipping_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('balance_due', 15, 2)->default(0);
            $table->string('status', 50)->default('draft'); // draft, sent, posted, cancelled
            $table->string('payment_status', 50)->default('unpaid'); // unpaid, partial, paid, overpaid
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            // Idempotency: prevent duplicate creates on retries
            $table->string('idempotency_key', 128)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->unique(['company_id', 'invoice_number']);
        });

        Schema::table('acct.invoices', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
            $table->foreign('customer_id')->references('customer_id')->on('hrm.customers')->onDelete('set null');
            $table->foreign('currency_id')->references('id')->on('public.currencies')->onDelete('restrict');
            $table->foreign('created_by')->references('user_id')->on('auth.user_accounts')->onDelete('set null');
            $table->foreign('updated_by')->references('user_id')->on('auth.user_accounts')->onDelete('set null');
            $table->index(['company_id', 'invoice_date'], 'idx_invoices_dates');
            $table->index(['company_id', 'status'], 'idx_invoices_status');
        });

        // Add check constraints
        DB::statement('ALTER TABLE acct.invoices ADD CONSTRAINT chk_due_date CHECK (due_date >= invoice_date)');
        DB::statement('ALTER TABLE acct.invoices ADD CONSTRAINT chk_subtotal_nonneg CHECK (subtotal >= 0)');
        DB::statement('ALTER TABLE acct.invoices ADD CONSTRAINT chk_tax_nonneg CHECK (tax_amount >= 0)');
        DB::statement('ALTER TABLE acct.invoices ADD CONSTRAINT chk_discount_nonneg CHECK (discount_amount >= 0)');
        DB::statement('ALTER TABLE acct.invoices ADD CONSTRAINT chk_shipping_nonneg CHECK (shipping_amount >= 0)');
        DB::statement('ALTER TABLE acct.invoices ADD CONSTRAINT chk_total_nonneg CHECK (total_amount >= 0)');
        DB::statement('ALTER TABLE acct.invoices ADD CONSTRAINT chk_paid_nonneg CHECK (paid_amount >= 0)');
        DB::statement('ALTER TABLE acct.invoices ADD CONSTRAINT chk_balance_nonneg CHECK (balance_due >= 0)');
        // Enum-like constraints for status fields
        DB::statement("ALTER TABLE acct.invoices ADD CONSTRAINT chk_invoice_status_valid CHECK (status IN ('draft','sent','posted','partial','paid','cancelled'))");
        DB::statement("ALTER TABLE acct.invoices ADD CONSTRAINT chk_payment_status_valid CHECK (payment_status IN ('unpaid','partial','paid','overpaid'))");

        // Idempotency unique scope within company
        try {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS invoices_idemp_unique ON acct.invoices (company_id, idempotency_key) WHERE idempotency_key IS NOT NULL');
        } catch (Throwable $e) { /* ignore on unsupported drivers */
        }

        // Enable RLS and tenant policy
        DB::statement('ALTER TABLE acct.invoices ENABLE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY invoices_tenant_isolation ON acct.invoices
            USING (company_id = current_setting('app.current_company', true)::uuid)
            WITH CHECK (company_id = current_setting('app.current_company', true)::uuid);
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acct.invoices');
    }
};
