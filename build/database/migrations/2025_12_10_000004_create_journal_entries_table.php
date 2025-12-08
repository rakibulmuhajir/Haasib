<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acct.journal_entries', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('transaction_id');
            $table->uuid('account_id');
            $table->integer('line_number');
            $table->text('description')->nullable();
            $table->decimal('debit_amount', 15, 2)->default(0.00);
            $table->decimal('credit_amount', 15, 2)->default(0.00);
            $table->decimal('currency_debit', 18, 6)->nullable();
            $table->decimal('currency_credit', 18, 6)->nullable();
            $table->decimal('exchange_rate', 18, 8)->nullable();
            $table->string('reference_type', 100)->nullable();
            $table->uuid('reference_id')->nullable();
            $table->string('dimension_1', 100)->nullable();
            $table->string('dimension_2', 100)->nullable();
            $table->string('dimension_3', 100)->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('transaction_id')->references('id')->on('acct.transactions')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('account_id')->references('id')->on('acct.accounts')->restrictOnDelete()->cascadeOnUpdate();

            $table->unique(['transaction_id', 'line_number']);
            $table->index('company_id');
            $table->index('transaction_id');
            $table->index('account_id');
            $table->index(['company_id', 'account_id']);
        });

        DB::statement("
            ALTER TABLE acct.journal_entries
            ADD CONSTRAINT journal_entries_debit_credit_chk
            CHECK (
                (debit_amount > 0 AND credit_amount = 0)
                OR (credit_amount > 0 AND debit_amount = 0)
            )
        ");

        DB::statement("ALTER TABLE acct.journal_entries ENABLE ROW LEVEL SECURITY");
        DB::statement("
            CREATE POLICY journal_entries_policy ON acct.journal_entries
            FOR ALL
            USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR coalesce(current_setting('app.is_super_admin', true)::boolean, false)
            )
        ");

        DB::statement("
            CREATE OR REPLACE FUNCTION acct.recompute_transaction_totals()
            RETURNS trigger AS $$
            BEGIN
              UPDATE acct.transactions t
              SET total_debit = COALESCE(s.sum_debit, 0),
                  total_credit = COALESCE(s.sum_credit, 0)
              FROM (
                SELECT transaction_id,
                       SUM(debit_amount) AS sum_debit,
                       SUM(credit_amount) AS sum_credit
                FROM acct.journal_entries
                WHERE transaction_id = COALESCE(NEW.transaction_id, OLD.transaction_id)
                GROUP BY transaction_id
              ) s
              WHERE t.id = COALESCE(NEW.transaction_id, OLD.transaction_id);
              RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;
        ");

        DB::statement("
            CREATE TRIGGER journal_entries_aiud
            AFTER INSERT OR UPDATE OR DELETE ON acct.journal_entries
            FOR EACH ROW
            EXECUTE FUNCTION acct.recompute_transaction_totals();
        ");
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS journal_entries_aiud ON acct.journal_entries');
        DB::statement('DROP FUNCTION IF EXISTS acct.recompute_transaction_totals()');
        Schema::dropIfExists('acct.journal_entries');
    }
};
