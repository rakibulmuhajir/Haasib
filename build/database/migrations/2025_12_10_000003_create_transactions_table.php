<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acct.transactions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->string('transaction_number', 50);
            $table->string('transaction_type', 30);
            $table->string('reference_type', 100)->nullable();
            $table->uuid('reference_id')->nullable();
            $table->date('transaction_date');
            $table->date('posting_date')->default(DB::raw('CURRENT_DATE'));
            $table->uuid('fiscal_year_id');
            $table->uuid('period_id');
            $table->text('description')->nullable();
            $table->char('currency', 3);
            $table->char('base_currency', 3);
            $table->decimal('exchange_rate', 18, 8)->nullable();
            $table->decimal('total_debit', 15, 2)->default(0.00);
            $table->decimal('total_credit', 15, 2)->default(0.00);
            $table->string('status', 20)->default('draft');
            $table->uuid('reversal_of_id')->nullable();
            $table->uuid('reversed_by_id')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->uuid('posted_by_user_id')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->uuid('voided_by_user_id')->nullable();
            $table->string('void_reason', 255)->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('fiscal_year_id')->references('id')->on('acct.fiscal_years')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('period_id')->references('id')->on('acct.accounting_periods')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('currency')->references('code')->on('public.currencies');
            $table->foreign('base_currency')->references('code')->on('public.currencies');
              $table->foreign('posted_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('voided_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('updated_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();

            $table->unique(['company_id', 'transaction_number'])->whereNull('deleted_at');
            $table->index('company_id');
            $table->index(['company_id', 'transaction_date']);
            $table->index(['company_id', 'status']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('fiscal_year_id');
            $table->index('period_id');
        });

        DB::statement("
            ALTER TABLE acct.transactions
            ADD CONSTRAINT transactions_balance_chk
            CHECK (total_debit = total_credit AND total_debit >= 0 AND total_credit >= 0)
        ");

        DB::statement("ALTER TABLE acct.transactions ENABLE ROW LEVEL SECURITY");
        DB::statement("
            CREATE POLICY transactions_policy ON acct.transactions
            FOR ALL
            USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR coalesce(current_setting('app.is_super_admin', true)::boolean, false)
            )
        ");

        DB::statement("
            CREATE OR REPLACE FUNCTION acct.check_period_open()
            RETURNS trigger AS $$
            DECLARE v_closed boolean;
            BEGIN
              SELECT is_closed INTO v_closed FROM acct.accounting_periods WHERE id = NEW.period_id;
              IF v_closed THEN
                RAISE EXCEPTION 'Cannot post to closed period %', NEW.period_id;
              END IF;
              RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");

        // Add self-referential foreign keys after table creation
        Schema::table('acct.transactions', function (Blueprint $table) {
            $table->foreign('reversal_of_id')->references('id')->on('acct.transactions')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('reversed_by_id')->references('id')->on('acct.transactions')->nullOnDelete()->cascadeOnUpdate();
        });

        DB::statement("
            CREATE TRIGGER transactions_biu_period
            BEFORE INSERT OR UPDATE ON acct.transactions
            FOR EACH ROW
            EXECUTE FUNCTION acct.check_period_open();
        ");
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS transactions_biu_period ON acct.transactions');
        DB::statement('DROP FUNCTION IF EXISTS acct.check_period_open()');
        Schema::dropIfExists('acct.transactions');
    }
};
