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
        if (Schema::hasTable('acct.customers')) {
            return;
        }

        DB::statement('CREATE SCHEMA IF NOT EXISTS acct');

        Schema::create('acct.customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('customer_number', 50);
            $table->string('name', 255);
            $table->string('email', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->jsonb('billing_address')->nullable();
            $table->jsonb('shipping_address')->nullable();
            $table->string('tax_id', 100)->nullable();
            $table->char('base_currency', 3)->default('USD');
            $table->integer('payment_terms')->default(30);
            $table->decimal('credit_limit', 15, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('logo_url', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->nullOnDelete();
            $table->foreign('updated_by_user_id')->references('id')->on('auth.users')->nullOnDelete();

            $table->index('company_id');
        });

        // Partial/conditional indexes for uniqueness and activity filters
        DB::statement('CREATE UNIQUE INDEX customers_company_number_unique ON acct.customers (company_id, customer_number) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX customers_company_email_unique ON acct.customers (company_id, email) WHERE email IS NOT NULL AND deleted_at IS NULL');
        DB::statement('CREATE INDEX customers_company_active_index ON acct.customers (company_id, is_active) WHERE deleted_at IS NULL');

        // Check constraints
        DB::statement("ALTER TABLE acct.customers ADD CONSTRAINT customers_base_currency_format CHECK (base_currency ~ '^[A-Z]{3}$')");
        DB::statement("ALTER TABLE acct.customers ADD CONSTRAINT customers_payment_terms_range CHECK (payment_terms >= 0 AND payment_terms <= 365)");
        DB::statement("ALTER TABLE acct.customers ADD CONSTRAINT customers_credit_limit_non_negative CHECK (credit_limit IS NULL OR credit_limit >= 0)");

        // Enable RLS and add company isolation with super-admin override
        DB::statement('ALTER TABLE acct.customers ENABLE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY customers_company_policy ON acct.customers
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
        DB::statement('DROP POLICY IF EXISTS customers_company_policy ON acct.customers');
        DB::statement('ALTER TABLE acct.customers DISABLE ROW LEVEL SECURITY');
        Schema::dropIfExists('acct.customers');
    }
};
