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
        if (Schema::hasTable('acct.payments')) {
            return;
        }

        DB::statement('CREATE SCHEMA IF NOT EXISTS acct');

        Schema::create('acct.payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('customer_id');
            $table->string('payment_number', 50);
            $table->date('payment_date')->default(DB::raw('current_date'));
            $table->decimal('amount', 18, 6)->default(0.00);
            $table->char('currency', 3)->default('USD');
            $table->decimal('exchange_rate', 18, 8)->nullable();
            $table->char('base_currency', 3);
            $table->decimal('base_amount', 15, 2)->default(0.00);
            $table->string('payment_method', 50);
            $table->string('reference_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete();
            $table->foreign('customer_id')->references('id')->on('acct.customers')->restrictOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->nullOnDelete();
            $table->foreign('updated_by_user_id')->references('id')->on('auth.users')->nullOnDelete();

            $table->index('company_id');
            $table->index('customer_id');
            $table->index('payment_date');
        });

        // Soft-delete aware unique payment number per company
        DB::statement('CREATE UNIQUE INDEX payments_company_number_unique ON acct.payments (company_id, payment_number) WHERE deleted_at IS NULL');

        // Check constraints
        DB::statement("ALTER TABLE acct.payments ADD CONSTRAINT payments_currency_format CHECK (currency ~ '^[A-Z]{3}$')");
        DB::statement("ALTER TABLE acct.payments ADD CONSTRAINT payments_base_currency_format CHECK (base_currency ~ '^[A-Z]{3}$')");
        DB::statement("ALTER TABLE acct.payments ADD CONSTRAINT payments_amount_positive CHECK (amount > 0)");
        DB::statement("ALTER TABLE acct.payments ADD CONSTRAINT payments_base_amount_non_negative CHECK (base_amount >= 0)");
        DB::statement("ALTER TABLE acct.payments ADD CONSTRAINT payments_payment_method_enum CHECK (payment_method IN ('cash','check','card','bank_transfer','other'))");

        // Enable RLS
        DB::statement('ALTER TABLE acct.payments ENABLE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY payments_company_policy ON acct.payments
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
        DB::statement('DROP POLICY IF EXISTS payments_company_policy ON acct.payments');
        DB::statement('ALTER TABLE acct.payments DISABLE ROW LEVEL SECURITY');
        Schema::dropIfExists('acct.payments');
    }
};
