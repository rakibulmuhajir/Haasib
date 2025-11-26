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
        Schema::create('acct.account_classes', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('generate_uuid()'));
            $table->string('name', 100); // Asset, Liability, Equity, Revenue, Expense
            $table->string('normal_balance', 10); // debit, credit
            $table->string('type', 50); // balance_sheet, income_statement
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['name', 'is_active']);
        });

        Schema::create('acct.account_groups', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('generate_uuid()'));
            $table->uuid('account_class_id');
            $table->string('name', 200);
            $table->string('code', 50)->nullable();
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('account_class_id')
                ->references('id')
                ->on('acct.account_classes')
                ->onDelete('cascade');

            $table->index(['account_class_id', 'is_active']);
        });

        Schema::create('acct.accounts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('generate_uuid()'));
            $table->uuid('company_id');
            $table->uuid('account_group_id');
            $table->string('code', 50);
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('normal_balance', 10); // debit, credit
            $table->boolean('is_active')->default(true);
            $table->boolean('allow_manual_entries')->default(true);
            $table->string('account_type', 100); // cash, bank, receivable, payable, etc.
            $table->string('currency', 3)->default('USD');
            $table->decimal('opening_balance', 20, 2)->default(0);
            $table->date('opening_balance_date')->nullable();
            $table->uuid('parent_id')->nullable();
            $table->timestamps();

            $table->foreign('company_id')
                ->references('id')
                ->on('auth.companies')
                ->onDelete('cascade');

            $table->foreign('account_group_id')
                ->references('id')
                ->on('acct.account_groups')
                ->onDelete('restrict');

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_active']);
            $table->index(['account_type', 'is_active']);
        });

        // Add self-referential foreign key after primary key exists
        Schema::table('acct.accounts', function (Blueprint $table) {
            $table->foreign('parent_id')
                ->references('id')
                ->on('acct.accounts')
                ->onDelete('cascade');
        });

        // Enable RLS on accounting tables
        DB::statement('ALTER TABLE acct.accounts ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE acct.account_groups ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE acct.account_classes ENABLE ROW LEVEL SECURITY');

        // Force RLS to ensure even table owner bypasses policies
        DB::statement('ALTER TABLE acct.accounts FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE acct.account_groups FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE acct.account_classes FORCE ROW LEVEL SECURITY');

        // Create RLS policies
        DB::statement('
            CREATE POLICY accounts_company_policy ON acct.accounts
            FOR ALL
            TO authenticated
            USING (company_id = acct.company_id_policy()::uuid)
            WITH CHECK (company_id = acct.company_id_policy()::uuid)
        ');

        DB::statement('
            CREATE POLICY account_groups_read_policy ON acct.account_groups
            FOR SELECT
            TO authenticated
            USING (is_active = true)
        ');

        DB::statement('
            CREATE POLICY account_classes_read_policy ON acct.account_classes
            FOR SELECT
            TO authenticated
            USING (is_active = true)
        ');

        // Insert default account classes
        DB::table('acct.account_classes')->insert([
            ['id' => DB::raw('generate_uuid()'), 'name' => 'Assets', 'normal_balance' => 'debit', 'type' => 'balance_sheet', 'order' => 1],
            ['id' => DB::raw('generate_uuid()'), 'name' => 'Liabilities', 'normal_balance' => 'credit', 'type' => 'balance_sheet', 'order' => 2],
            ['id' => DB::raw('generate_uuid()'), 'name' => 'Equity', 'normal_balance' => 'credit', 'type' => 'balance_sheet', 'order' => 3],
            ['id' => DB::raw('generate_uuid()'), 'name' => 'Revenue', 'normal_balance' => 'credit', 'type' => 'income_statement', 'order' => 4],
            ['id' => DB::raw('generate_uuid()'), 'name' => 'Expenses', 'normal_balance' => 'debit', 'type' => 'income_statement', 'order' => 5],
            ['id' => DB::raw('generate_uuid()'), 'name' => 'Cost of Goods Sold', 'normal_balance' => 'debit', 'type' => 'income_statement', 'order' => 6],
            ['id' => DB::raw('generate_uuid()'), 'name' => 'Other Income', 'normal_balance' => 'credit', 'type' => 'income_statement', 'order' => 7],
            ['id' => DB::raw('generate_uuid()'), 'name' => 'Other Expenses', 'normal_balance' => 'debit', 'type' => 'income_statement', 'order' => 8],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop policies
        DB::statement('DROP POLICY IF EXISTS accounts_company_policy ON acct.accounts');
        DB::statement('DROP POLICY IF EXISTS account_groups_read_policy ON acct.account_groups');
        DB::statement('DROP POLICY IF EXISTS account_classes_read_policy ON acct.account_classes');

        // Disable RLS
        DB::statement('ALTER TABLE acct.accounts DISABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE acct.account_groups DISABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE acct.account_classes DISABLE ROW LEVEL SECURITY');

        // Drop tables
        Schema::dropIfExists('acct.accounts');
        Schema::dropIfExists('acct.account_groups');
        Schema::dropIfExists('acct.account_classes');
    }
};
