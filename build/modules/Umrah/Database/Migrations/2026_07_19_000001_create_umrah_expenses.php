<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('umrah.expenses', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->string('expense_number', 50);
            $table->date('expense_date');
            $table->uuid('expense_account_id');
            $table->uuid('payment_account_id');
            $table->string('payee')->nullable();
            $table->text('description');
            $table->string('reference')->nullable();
            $table->decimal('amount', 18, 6);
            $table->char('currency', 3);
            $table->decimal('exchange_rate', 18, 8)->nullable();
            $table->char('base_currency', 3);
            $table->decimal('base_amount', 15, 2);
            $table->uuid('transaction_id')->nullable();
            $table->string('status', 20)->default('posted');
            $table->timestamp('reversed_at')->nullable();
            $table->uuid('reversed_by_user_id')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->uuid('reversal_transaction_id')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('expense_account_id')->references('id')->on('acct.accounts')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('payment_account_id')->references('id')->on('acct.accounts')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('currency')->references('code')->on('public.currencies')->cascadeOnUpdate();
            $table->foreign('base_currency')->references('code')->on('public.currencies')->cascadeOnUpdate();
            $table->foreign('transaction_id')->references('id')->on('acct.transactions')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('reversed_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('reversal_transaction_id')->references('id')->on('acct.transactions')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();
            $table->unique(['company_id', 'expense_number']);
            $table->index(['company_id', 'expense_date']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'expense_account_id']);
        });

        DB::statement("ALTER TABLE umrah.expenses ADD CONSTRAINT umrah_expenses_status_check CHECK (status IN ('posted', 'reversed'))");
        DB::statement('ALTER TABLE umrah.expenses ADD CONSTRAINT umrah_expenses_amount_check CHECK (amount > 0 AND base_amount > 0)');
        DB::statement('ALTER TABLE umrah.expenses ADD CONSTRAINT umrah_expenses_exchange_rate_check CHECK ((currency = base_currency AND exchange_rate IS NULL AND base_amount = round(amount, 2)) OR (currency <> base_currency AND exchange_rate > 0 AND base_amount = round(amount * exchange_rate, 2)))');
        DB::statement('ALTER TABLE umrah.expenses ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE umrah.expenses FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY expenses_company_isolation ON umrah.expenses FOR ALL USING (company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid) WITH CHECK (company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid)");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS expenses_company_isolation ON umrah.expenses');
        Schema::dropIfExists('umrah.expenses');
    }
};
