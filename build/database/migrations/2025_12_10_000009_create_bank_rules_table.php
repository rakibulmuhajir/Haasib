<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank.bank_rules', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('bank_account_id')->nullable();
            $table->string('name', 255);
            $table->integer('priority')->default(0);
            $table->jsonb('conditions');
            $table->jsonb('actions');
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('bank_account_id')->references('id')->on('bank.company_bank_accounts')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();

            $table->index('company_id');
            $table->index(['company_id', 'is_active', 'priority']);
        });

        DB::statement("ALTER TABLE bank.bank_rules ENABLE ROW LEVEL SECURITY");
        DB::statement("
            CREATE POLICY bank_rules_policy ON bank.bank_rules
            FOR ALL USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");

        // Add check constraints for valid JSON structure
        DB::statement("
            ALTER TABLE bank.bank_rules
            ADD CONSTRAINT bank_rules_conditions_valid_chk
            CHECK (jsonb_typeof(conditions) = 'object' AND conditions IS NOT NULL)
        ");

        DB::statement("
            ALTER TABLE bank.bank_rules
            ADD CONSTRAINT bank_rules_actions_valid_chk
            CHECK (jsonb_typeof(actions) = 'object' AND actions IS NOT NULL)
        ");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS bank_rules_policy ON bank.bank_rules');
        Schema::dropIfExists('bank.bank_rules');
    }
};