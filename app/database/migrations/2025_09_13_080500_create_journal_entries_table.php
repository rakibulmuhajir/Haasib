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
        if (Schema::hasTable('acct.journal_entries')) {
            // Table already created by an earlier migration; skip to avoid duplicates/conflicts
            return;
        }

        Schema::create('acct.journal_entries', function (Blueprint $table) {
            $table->id('entry_id');
            $table->foreignId('transaction_id')->constrained('acct.transactions', 'transaction_id');
            $table->foreignId('account_id')->constrained('acct.chart_of_accounts', 'account_id');
            $table->decimal('debit_amount', 15, 2)->default(0);
            $table->decimal('credit_amount', 15, 2)->default(0);
            $table->text('description')->nullable();
            $table->string('reference_type', 50)->nullable();
            $table->bigInteger('reference_id')->nullable();
            $table->integer('sort_order')->default(0);
            $table->uuid('functional_currency_id')->nullable();
            $table->decimal('fx_rate', 20, 10)->nullable();
            $table->decimal('functional_debit', 15, 2)->default(0);
            $table->decimal('functional_credit', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::table('acct.journal_entries', function (Blueprint $table) {
            $table->foreign('functional_currency_id')->references('id')->on('public.currencies')->onDelete('set null');
        });

        // Add check constraint: either debit or credit must be positive, not both
        DB::statement('ALTER TABLE acct.journal_entries ADD CONSTRAINT chk_debit_credit_xor CHECK ((debit_amount > 0 AND credit_amount = 0) OR (credit_amount > 0 AND debit_amount = 0))');
        DB::statement('ALTER TABLE acct.journal_entries ADD CONSTRAINT chk_debit_positive CHECK (debit_amount >= 0)');
        DB::statement('ALTER TABLE acct.journal_entries ADD CONSTRAINT chk_credit_positive CHECK (credit_amount >= 0)');
        DB::statement('ALTER TABLE acct.journal_entries ADD CONSTRAINT chk_functional_debit_positive CHECK (functional_debit >= 0)');
        DB::statement('ALTER TABLE acct.journal_entries ADD CONSTRAINT chk_functional_credit_positive CHECK (functional_credit >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acct.journal_entries');
    }
};
