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

        Schema::create('acct.customer_contacts', function (Blueprint $table) {
            // UUID Primary Key
            $table->uuid('id')->primary();

            // Business Data
            $table->uuid('customer_id');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('job_title')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('mobile_phone')->nullable();
            $table->boolean('is_primary_contact')->default(false);
            $table->boolean('is_billing_contact')->default(false);
            $table->boolean('is_technical_contact')->default(false);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->text('notes')->nullable();

            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Foreign Keys (cross-schema within acct)
            $table->foreign('customer_id')
                  ->references('id')
                  ->on('acct.customers')
                  ->onDelete('cascade');

            // Unique Constraints (per-customer)
            $table->unique(['customer_id', 'email']);
            $table->unique(['customer_id', 'first_name', 'last_name']);

            // Indexes for Performance
            $table->index(['customer_id', 'is_primary_contact']);
            $table->index(['customer_id', 'is_billing_contact']);
            $table->index(['customer_id', 'status']);
            $table->index(['email']);
            $table->index(['phone']);
        });

        // Enable RLS (Row Level Security)
        DB::statement('ALTER TABLE acct.customer_contacts ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE acct.customer_contacts FORCE ROW LEVEL SECURITY');

        // Create RLS Policy - inherits through customer relationship
        DB::statement("
            CREATE POLICY customer_contacts_company_policy ON acct.customer_contacts
            FOR ALL
            USING (EXISTS (
                SELECT 1 FROM acct.customers c 
                WHERE c.id = acct.customer_contacts.customer_id 
                AND c.company_id = current_setting('app.current_company_id', true)::uuid
            ))
            WITH CHECK (EXISTS (
                SELECT 1 FROM acct.customers c 
                WHERE c.id = acct.customer_contacts.customer_id 
                AND c.company_id = current_setting('app.current_company_id', true)::uuid
            ))
        ");

        // Create Audit Trigger (for business tables)
        DB::statement('
            CREATE TRIGGER customer_contacts_audit_trigger
            AFTER INSERT OR UPDATE OR DELETE ON acct.customer_contacts
            FOR EACH ROW EXECUTE FUNCTION audit.audit_log()
        ');
    }

    public function down(): void
    {
        // Clean up in reverse order
        DB::statement('DROP TRIGGER IF EXISTS customer_contacts_audit_trigger ON acct.customer_contacts');
        DB::statement('DROP POLICY IF EXISTS customer_contacts_company_policy ON acct.customer_contacts');
        Schema::dropIfExists('acct.customer_contacts');
    }
};
