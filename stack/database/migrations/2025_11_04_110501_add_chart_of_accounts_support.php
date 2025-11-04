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
        // Create a simple chart of accounts table for accounting sub-ledger
        Schema::create('acct.chart_of_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('account_number', 20);
            $table->string('account_name');
            $table->string('account_type', 20); // Asset, Liability, Equity, Revenue, Expense
            $table->string('account_category', 50)->nullable(); // Current Assets, Accounts Payable, etc.
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->date('opening_balance_date')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Unique constraint per company
            $table->unique(['company_id', 'account_number']);

            // Foreign keys
            $table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');

            // Indexes
            $table->index(['company_id']);
            $table->index(['company_id', 'account_number']);
            $table->index(['company_id', 'account_type']);
            $table->index(['account_type']);
            $table->index(['account_number']);
        });

        // Enable RLS
        DB::statement('ALTER TABLE acct.chart_of_accounts ENABLE ROW LEVEL SECURITY');

        // Create RLS policies
        DB::statement("
            CREATE POLICY chart_of_accounts_company_policy ON acct.chart_of_accounts
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
            ALTER TABLE acct.chart_of_accounts
            ADD CONSTRAINT chart_of_accounts_valid_type
            CHECK (account_type IN (\'Asset\', \'Liability\', \'Equity\', \'Revenue\', \'Expense\'))
        ');

        DB::statement('
            ALTER TABLE acct.chart_of_accounts
            ADD CONSTRAINT chart_of_accounts_opening_balance_valid
            CHECK (opening_balance >= 0)
        ');

        // Update journal_lines to reference chart of accounts
        Schema::table('acct.journal_lines', function (Blueprint $table) {
            // Add proper account reference
            $table->uuid('account_id')->nullable()->after('journal_entry_id');

            // Add foreign key to chart of accounts
            $table->foreign('account_id')->references('id')->on('acct.chart_of_accounts')->onDelete('restrict');

            // Add index for account_id
            $table->index(['account_id']);
            $table->index(['company_id', 'account_id']);
        });

        // Add constraint to ensure either account_id or manual account info is provided
        DB::statement('
            ALTER TABLE acct.journal_lines
            ADD CONSTRAINT journal_lines_account_reference_required
            CHECK (
                (account_id IS NOT NULL) OR
                (account_number IS NOT NULL AND account_name IS NOT NULL)
            )
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('acct.journal_lines', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->dropIndex(['account_id']);
            $table->dropIndex(['company_id', 'account_id']);
            $table->dropColumn('account_id');
        });

        DB::statement('DROP CONSTRAINT IF EXISTS journal_lines_account_reference_required');
        DB::statement('DROP POLICY IF EXISTS chart_of_accounts_company_policy ON acct.chart_of_accounts');
        DB::statement('ALTER TABLE acct.chart_of_accounts DISABLE ROW LEVEL SECURITY');
        Schema::dropIfExists('acct.chart_of_accounts');
    }
};