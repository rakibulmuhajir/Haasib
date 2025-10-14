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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id('transaction_id');
            $table->uuid('company_id');
            $table->string('transaction_number', 100);
            $table->string('transaction_type', 50); // journal_entry, invoice, bill, payment, receipt
            $table->string('reference_type', 50)->nullable();
            $table->bigInteger('reference_id')->nullable();
            $table->date('transaction_date');
            $table->text('description')->nullable();
            $table->uuid('currency_id');
            $table->decimal('exchange_rate', 20, 10)->default(1);
            $table->decimal('total_debit', 15, 2)->default(0);
            $table->decimal('total_credit', 15, 2)->default(0);
            $table->string('status', 50)->default('posted');
            $table->foreignId('reversal_transaction_id')->nullable()->constrained('transactions', 'transaction_id');
            $table->foreignId('period_id')->nullable()->constrained('accounting_periods', 'period_id');
            $table->timestamps();
            $table->softDeletes();

            $table->foreignId('created_by')->nullable()->constrained('user_accounts', 'user_id');
            $table->foreignId('updated_by')->nullable()->constrained('user_accounts', 'user_id');

            $table->unique(['company_id', 'transaction_number']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
            $table->foreign('currency_id')->references('id')->on('currencies')->onDelete('restrict');
        });

        // Add check constraints
        DB::statement('ALTER TABLE transactions ADD CONSTRAINT chk_totals_equal CHECK (total_debit = total_credit)');
        DB::statement('ALTER TABLE transactions ADD CONSTRAINT chk_debit_positive CHECK (total_debit >= 0)');
        DB::statement('ALTER TABLE transactions ADD CONSTRAINT chk_credit_positive CHECK (total_credit >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
