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
        Schema::create('acct.vendor_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('vendor_id');
            $table->enum('contact_type', ['primary', 'billing', 'technical', 'other'])->default('primary');
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('mobile', 50)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            // Indexes
            $table->index(['vendor_id']);
            $table->index(['vendor_id', 'contact_type']);
            $table->index(['vendor_id', 'is_primary']);
        });

        // Enable RLS
        DB::statement('ALTER TABLE acct.vendor_contacts ENABLE ROW LEVEL SECURITY');

        // Create RLS policies
        DB::statement("
            CREATE POLICY vendor_contacts_company_policy ON acct.vendor_contacts
            FOR ALL
            USING (
                vendor_id IN (
                    SELECT id FROM acct.vendors 
                    WHERE company_id = current_setting('app.current_company_id', true)::uuid
                )
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
            WITH CHECK (
                vendor_id IN (
                    SELECT id FROM acct.vendors 
                    WHERE company_id = current_setting('app.current_company_id', true)::uuid
                )
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");

        // Add foreign key constraint after table creation
        DB::statement('
            ALTER TABLE acct.vendor_contacts
            ADD CONSTRAINT vendor_contacts_vendor_id_foreign
            FOREIGN KEY (vendor_id) REFERENCES acct.vendors(id) ON DELETE CASCADE
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS vendor_contacts_company_policy ON acct.vendor_contacts');
        DB::statement('ALTER TABLE acct.vendor_contacts DISABLE ROW LEVEL SECURITY');
        Schema::dropIfExists('acct.vendor_contacts');
    }
};
