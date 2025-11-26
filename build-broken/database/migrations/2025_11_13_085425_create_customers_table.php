<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create schema if not exists
        DB::statement('CREATE SCHEMA IF NOT EXISTS acct');

        Schema::create('acct.customers', function (Blueprint $table) {
            // UUID Primary Key
            $table->uuid('id')->primary();

            // Tenant Isolation
            $table->uuid('company_id');

            // Business Data
            $table->string('customer_number', 50);
            $table->string('name');
            $table->string('display_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->text('address_line_1')->nullable();
            $table->text('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state_province')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->default('US');
            $table->string('tax_id')->nullable();
            $table->enum('customer_type', ['individual', 'business', 'government', 'non_profit'])->default('business');
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->decimal('credit_limit', 12, 2)->default(0);
            $table->enum('payment_terms', ['net_15', 'net_30', 'net_45', 'net_60', 'due_on_receipt'])->default('net_30');
            $table->text('notes')->nullable();
            $table->uuid('created_by_user_id');

            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Foreign Keys (cross-schema)
            $table->foreign('company_id')
                  ->references('id')
                  ->on('auth.companies')
                  ->onDelete('cascade');
            $table->foreign('created_by_user_id')
                  ->references('id')
                  ->on('auth.users')
                  ->onDelete('cascade');

            // Unique Constraints (per-tenant)
            $table->unique(['company_id', 'customer_number']);
            $table->unique(['company_id', 'email']);

            // Indexes for Performance
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'name']);
            $table->index(['company_id', 'customer_type']);
            $table->index(['company_id', 'email']);
            $table->index(['company_id', 'phone']);
        });

        // Enable RLS (Row Level Security)
        DB::statement('ALTER TABLE acct.customers ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE acct.customers FORCE ROW LEVEL SECURITY');

        // Create RLS Policy
        DB::statement("
            CREATE POLICY customers_company_policy ON acct.customers
            FOR ALL
            USING (company_id = current_setting('app.current_company_id', true)::uuid)
            WITH CHECK (company_id = current_setting('app.current_company_id', true)::uuid)
        ");

        // Create Audit Trigger (for financial/business tables)
        DB::statement('
            CREATE TRIGGER customers_audit_trigger
            AFTER INSERT OR UPDATE OR DELETE ON acct.customers
            FOR EACH ROW EXECUTE FUNCTION audit.audit_log()
        ');

        // Create Function for Customer Number Generation
        DB::statement('
            CREATE OR REPLACE FUNCTION acct.generate_customer_number(p_company_id UUID)
            RETURNS TEXT AS $$
            DECLARE
                next_number INTEGER;
            BEGIN
                SELECT COALESCE(MAX(CAST(SUBSTRING(customer_number FROM 6) AS INTEGER)), 0) + 1
                INTO next_number
                FROM acct.customers
                WHERE company_id = p_company_id;

                RETURN \'CUST-\' || LPAD(next_number::TEXT, 5, \'0\');
            END;
            $$ LANGUAGE plpgsql;
        ');
    }

    public function down(): void
    {
        // Clean up in reverse order
        DB::statement('DROP FUNCTION IF EXISTS acct.generate_customer_number(UUID)');
        DB::statement('DROP TRIGGER IF EXISTS customers_audit_trigger ON acct.customers');
        DB::statement('DROP POLICY IF EXISTS customers_company_policy ON acct.customers');
        Schema::dropIfExists('acct.customers');
    }
};
