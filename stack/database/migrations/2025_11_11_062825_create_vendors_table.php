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
        Schema::create('acct.vendors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('vendor_code', 50);
            $table->string('legal_name', 255);
            $table->string('display_name', 255)->nullable();
            $table->string('tax_id', 50)->nullable(); // EIN, SSN, etc.
            $table->enum('vendor_type', ['individual', 'company', 'other'])->default('company');
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->string('website', 255)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['company_id']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'vendor_code']);
            $table->unique(['company_id', 'vendor_code']);
        });

        // Enable RLS
        DB::statement('ALTER TABLE acct.vendors ENABLE ROW LEVEL SECURITY');

        // Create RLS policies
        DB::statement("
            CREATE POLICY vendors_company_policy ON acct.vendors
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

        // Add foreign key constraint after table creation
        DB::statement('
            ALTER TABLE acct.vendors
            ADD CONSTRAINT vendors_company_id_foreign
            FOREIGN KEY (company_id) REFERENCES auth.companies(id) ON DELETE CASCADE
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS vendors_company_policy ON acct.vendors');
        DB::statement('ALTER TABLE acct.vendors DISABLE ROW LEVEL SECURITY');
        Schema::dropIfExists('acct.vendors');
    }
};
