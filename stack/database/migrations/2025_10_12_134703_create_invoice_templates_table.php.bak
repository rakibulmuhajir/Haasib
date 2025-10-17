<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades.DB;
use Illuminate\Support\Facades.Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('acct.invoice_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->uuid('customer_id')->nullable();
            $table->string('currency', 3);
            $table->jsonb('template_data');
            $table->jsonb('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by_user_id');
            $table->timestamps();
            $table->softDeletes();

            // Foreign key constraints
            $table->foreign('company_id')
                ->references('id')
                ->on('auth.companies')
                ->onDelete('cascade');

            $table->foreign('customer_id')
                ->references('id')
                ->on('acct.customers')
                ->onDelete('set null');

            $table->foreign('created_by_user_id')
                ->references('id')
                ->on('auth.users')
                ->onDelete('cascade');

            // Indexes for performance
            $table->index(['company_id', 'is_active']);
            $table->index(['company_id', 'customer_id']);
            $table->index(['currency']);
            $table->index(['is_active']);
            $table->index(['created_by_user_id']);
        });

        DB::statement('ALTER TABLE acct.invoice_templates ENABLE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY invoice_templates_company_policy
            ON acct.invoice_templates
            FOR ALL
            USING (
                company_id = current_setting('app.current_company_id')::uuid
            )
            WITH CHECK (
                company_id = current_setting('app.current_company_id')::uuid
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS invoice_templates_company_policy ON acct.invoice_templates');
        DB::statement('ALTER TABLE acct.invoice_templates DISABLE ROW LEVEL SECURITY');
        Schema::dropIfExists('acct.invoice_templates');
    }
};
