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
            $table->decimal('credit_limit', 10, 2)->nullable();
            $table->string('payment_terms')->nullable();
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('company_id');
            $table->index('email');
            $table->index('customer_number');
            $table->index('is_active');
            $table->index(['company_id', 'is_active']);

            // Foreign key constraints
            $table->foreign('company_id')
                ->references('id')
                ->on('auth.companies')
                ->onDelete('cascade');

            $table->foreign('created_by_user_id')
                ->references('id')
                ->on('auth.users')
                ->onDelete('set null');
        });

        // Add soft deletes
        Schema::table('acct.customers', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Add RLS policies for PostgreSQL
        DB::statement('ALTER TABLE "acct.customers" ENABLE ROW LEVEL SECURITY');

        // Policy: Users can only see customers from their companies
        DB::statement("
            CREATE POLICY customers_select_policy ON \"acct.customers\"
            FOR SELECT
            USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            );
        ");

        // Policy: Company users can insert customers in their company
        DB::statement("
            CREATE POLICY customers_insert_policy ON \"acct.customers\"
            FOR INSERT
            WITH CHECK (
                company_id = current_setting('app.current_company_id', true)::uuid
            );
        ");

        // Policy: Users can update customers from their companies
        DB::statement("
            CREATE POLICY customers_update_policy ON \"acct.customers\"
            FOR UPDATE
            USING (
                company_id = current_setting('app.current_company_id', true)::uuid
            );
        ");

        // Policy: Users can delete customers from their companies
        DB::statement("
            CREATE POLICY customers_delete_policy ON \"acct.customers\"
            FOR DELETE
            USING (
                company_id = current_setting('app.current_company_id', true)::uuid
            );
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop RLS policies
        DB::statement('DROP POLICY IF EXISTS customers_select_policy ON "acct.customers"');
        DB::statement('DROP POLICY IF EXISTS customers_insert_policy ON "acct.customers"');
        DB::statement('DROP POLICY IF EXISTS customers_update_policy ON "acct.customers"');
        DB::statement('DROP POLICY IF EXISTS customers_delete_policy ON "acct.customers"');

        Schema::dropIfExists('acct.customers');
    }
};
