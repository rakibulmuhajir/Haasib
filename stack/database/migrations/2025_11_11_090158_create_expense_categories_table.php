<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acct.expense_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->enum('type', ['expense', 'reimbursement'])->default('expense');
            $table->boolean('is_active')->default(true);
            $table->uuid('parent_id')->nullable();
            $table->string('color', 7)->nullable(); // Hex color code
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            // Foreign keys
            $table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
            // Note: parent_id foreign key will be added after table creation to avoid circular reference

            // Indexes
            $table->index(['company_id', 'code']);
            $table->index(['company_id', 'is_active']);
            $table->index(['company_id', 'type']);
        });

        // Row Level Security policies
        DB::statement('
            ALTER TABLE acct.expense_categories ENABLE ROW LEVEL SECURITY;
        ');

        // RLS policy for company isolation
        DB::statement("
            CREATE POLICY expense_categories_company_isolation_policy ON acct.expense_categories
            FOR ALL TO app_user
            USING (company_id = current_setting('app.current_company_id')::uuid);
        ");

        // RLS policy for authenticated users in the app_user role
        DB::statement("
            CREATE POLICY expense_categories_app_user_policy ON acct.expense_categories
            FOR ALL TO app_user
            USING (company_id = current_setting('app.current_company_id')::uuid);
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('acct.expense_categories');
    }
};
