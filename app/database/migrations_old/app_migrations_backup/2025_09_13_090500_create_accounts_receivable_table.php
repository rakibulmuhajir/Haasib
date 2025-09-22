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
        Schema::create('accounts_receivable', function (Blueprint $table) {
            $table->uuid('ar_id')->primary();
            $table->uuid('company_id');
            $table->uuid('customer_id')->nullable();
            $table->uuid('invoice_id')->unique();
            $table->decimal('amount_due', 15, 2);
            $table->decimal('original_amount', 15, 2);
            $table->uuid('currency_id');
            $table->date('due_date');
            $table->integer('days_overdue')->default(0);
            $table->string('aging_category', 10)->default('current');
            $table->timestamp('last_calculated_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('company_id');
            $table->index('customer_id');
            $table->index('due_date');
        });

        Schema::table('accounts_receivable', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
            $table->foreign('customer_id')->references('customer_id')->on('customers')->onDelete('set null');
            $table->foreign('invoice_id')->references('invoice_id')->on('invoices')->onDelete('cascade');
            $table->foreign('currency_id')->references('id')->on('currencies')->onDelete('restrict');
        });

        // Add check constraints
        DB::statement('ALTER TABLE accounts_receivable ADD CONSTRAINT chk_amount_due_nonneg CHECK (amount_due >= 0)');
        DB::statement('ALTER TABLE accounts_receivable ADD CONSTRAINT chk_original_positive CHECK (original_amount > 0)');
        DB::statement('ALTER TABLE accounts_receivable ADD CONSTRAINT chk_days_overdue_nonneg CHECK (days_overdue >= 0)');

        // Enable RLS and tenant policy
        DB::statement('ALTER TABLE accounts_receivable ENABLE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY accounts_receivable_tenant_isolation ON accounts_receivable
            USING (company_id = current_setting('app.current_company', true)::uuid)
            WITH CHECK (company_id = current_setting('app.current_company', true)::uuid);
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts_receivable');
    }
};
