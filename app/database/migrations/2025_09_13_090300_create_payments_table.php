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
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('payment_id')->primary();
            $table->uuid('company_id');
            $table->string('payment_number', 100);
            $table->string('payment_type', 50)->default('customer_payment');
            $table->string('entity_type', 50)->default('customer');
            $table->uuid('entity_id')->nullable(); // Will be FK to crm.customers when available
            $table->bigInteger('bank_account_id')->nullable(); // Will be FK to bank.company_bank_accounts when available
            $table->string('payment_method', 50); // cash, bank_transfer, card
            $table->date('payment_date');
            $table->decimal('amount', 15, 2);
            $table->uuid('currency_id');
            $table->decimal('exchange_rate', 20, 10)->default(1);
            $table->string('reference_number', 100)->nullable();
            $table->string('check_number', 50)->nullable();
            $table->string('bank_txn_id', 100)->nullable();
            $table->string('status', 50)->default('pending');
            $table->boolean('reconciled')->default(false);
            $table->timestamp('reconciled_date')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreignId('created_by')->nullable()->constrained('user_accounts', 'user_id');
            $table->foreignId('updated_by')->nullable()->constrained('user_accounts', 'user_id');
            $table->foreignId('reconciled_by')->nullable()->constrained('user_accounts', 'user_id');

            $table->unique(['company_id', 'payment_number']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
            $table->foreign('currency_id')->references('id')->on('currencies')->onDelete('restrict');
            $table->index(['company_id', 'payment_date'], 'idx_payments_date');
        });

        // Add check constraints
        DB::statement('ALTER TABLE payments ADD CONSTRAINT chk_amount_positive CHECK (amount > 0)');

        // Enable RLS and tenant policy
        DB::statement('ALTER TABLE payments ENABLE ROW LEVEL SECURITY');
        DB::statement(<<<SQL
            CREATE POLICY payments_tenant_isolation ON payments
            USING (company_id = current_setting('app.current_company', true)::uuid)
            WITH CHECK (company_id = current_setting('app.current_company', true)::uuid);
        SQL);
        // Enum-like constraint for status
        DB::statement("ALTER TABLE payments ADD CONSTRAINT chk_payment_status_valid CHECK (status IN ('pending','completed','failed','cancelled'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
