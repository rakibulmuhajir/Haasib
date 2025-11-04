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
        Schema::create('acct.journal_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('journal_entry_id');
            $table->string('account_number');
            $table->string('account_name');
            $table->text('description');
            $table->decimal('debit_amount', 15, 2)->default(0);
            $table->decimal('credit_amount', 15, 2)->default(0);
            $table->uuid('created_by_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Foreign key
            $table->foreign('journal_entry_id')->references('id')->on('acct.journal_entries')->onDelete('cascade');
            $table->foreign('created_by_id')->references('id')->on('auth.users')->onDelete('set null');

            // Indexes
            $table->index(['company_id']);
            $table->index(['journal_entry_id']);
            $table->index(['company_id', 'journal_entry_id']);
            $table->index(['company_id', 'account_number']);
            $table->index(['account_number']);
            $table->index(['created_by_id']);
        });

        // Enable RLS
        DB::statement('ALTER TABLE acct.journal_lines ENABLE ROW LEVEL SECURITY');

        // Create RLS policies
        DB::statement("
            CREATE POLICY journal_lines_company_policy ON acct.journal_lines
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
            ALTER TABLE acct.journal_lines
            ADD CONSTRAINT journal_lines_amounts_positive
            CHECK (
                debit_amount >= 0
                AND credit_amount >= 0
            )
        ');

        DB::statement('
            ALTER TABLE acct.journal_lines
            ADD CONSTRAINT journal_lines_one_side_only
            CHECK (
                (debit_amount > 0 AND credit_amount = 0)
                OR (credit_amount > 0 AND debit_amount = 0)
                OR (debit_amount = 0 AND credit_amount = 0)
            )
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS journal_lines_company_policy ON acct.journal_lines');
        DB::statement('ALTER TABLE acct.journal_lines DISABLE ROW LEVEL SECURITY');
        Schema::dropIfExists('acct.journal_lines');
    }
};