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
        Schema::create('acct.customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('customer_number')->unique();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();
            $table->string('tax_id')->nullable();
            $table->string('website')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('credit_limit', 15, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->string('status', 20)->default('active');
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->date('opening_balance_date')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['company_id']);
            $table->index(['company_id', 'customer_number']);
            $table->index(['company_id', 'email']);
            $table->index(['company_id', 'status']);
            $table->index(['customer_number']);
            $table->index(['email']);
            $table->index(['status']);
        });

        // Enable RLS
        DB::statement('ALTER TABLE acct.customers ENABLE ROW LEVEL SECURITY');

        // Create RLS policies
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

        // Add constraints
        DB::statement('
            ALTER TABLE acct.customers
            ADD CONSTRAINT customers_credit_limit_positive
            CHECK (credit_limit >= 0)
        ');

        DB::statement('
            ALTER TABLE acct.customers
            ADD CONSTRAINT customers_opening_balance_positive
            CHECK (opening_balance >= 0)
        ');
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