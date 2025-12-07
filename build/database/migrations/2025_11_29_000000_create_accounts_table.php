<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acct.accounts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('parent_id')->nullable();
            $table->string('code', 50);
            $table->string('name', 255);
            $table->string('type', 30);
            $table->string('subtype', 50);
            $table->string('normal_balance', 6);
            $table->char('currency', 3)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->text('description')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            // Self FK added after table exists
            $table->foreign('currency')->references('code')->on('public.currencies');
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('updated_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();

            $table->unique(['company_id', 'code'])->whereNull('deleted_at');
            $table->index('company_id');
            $table->index(['company_id', 'type', 'subtype']);
            $table->index(['company_id', 'is_active']);
        });

        DB::statement("
            ALTER TABLE acct.accounts
            ADD CONSTRAINT accounts_normal_balance_chk
            CHECK (
                (type IN ('asset','expense','cogs') AND normal_balance = 'debit')
                OR (type IN ('liability','equity','revenue','other_income','other_expense') AND normal_balance = 'credit')
            )
        ");

        DB::statement("
            ALTER TABLE acct.accounts
            ADD CONSTRAINT accounts_currency_allowed_chk
            CHECK (
                currency IS NULL
                OR subtype IN ('bank','cash','accounts_receivable','accounts_payable','credit_card','other_current_asset','other_asset','other_current_liability','other_liability')
            )
        ");

        DB::statement("
            ALTER TABLE acct.accounts
            ADD CONSTRAINT accounts_parent_fk
            FOREIGN KEY (parent_id) REFERENCES acct.accounts(id)
            ON DELETE SET NULL ON UPDATE CASCADE
        ");

        DB::statement("ALTER TABLE acct.accounts ENABLE ROW LEVEL SECURITY");
        DB::statement("
            CREATE POLICY accounts_policy ON acct.accounts
            FOR ALL
            USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR coalesce(current_setting('app.is_super_admin', true)::boolean, false)
            )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('acct.accounts');
    }
};
