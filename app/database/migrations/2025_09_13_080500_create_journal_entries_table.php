<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id('entry_id');
            $table->foreignId('transaction_id')->constrained('transactions', 'transaction_id');
            $table->foreignId('account_id')->constrained('chart_of_accounts', 'account_id');
            $table->decimal('debit_amount', 15, 2)->default(0);
            $table->decimal('credit_amount', 15, 2)->default(0);
            $table->text('description')->nullable();
            $table->string('reference_type', 50)->nullable();
            $table->bigInteger('reference_id')->nullable();
            $table->integer('sort_order')->default(0);
            $table->foreignId('functional_currency_id')->nullable()->constrained('currencies');
            $table->decimal('fx_rate', 20, 10)->nullable();
            $table->decimal('functional_debit', 15, 2)->default(0);
            $table->decimal('functional_credit', 15, 2)->default(0);
            $table->timestamps();
        });
        
        // Add check constraint: either debit or credit must be positive, not both
        DB::statement('ALTER TABLE journal_entries ADD CONSTRAINT chk_debit_credit_xor CHECK ((debit_amount > 0 AND credit_amount = 0) OR (credit_amount > 0 AND debit_amount = 0))');
        DB::statement('ALTER TABLE journal_entries ADD CONSTRAINT chk_debit_positive CHECK (debit_amount >= 0)');
        DB::statement('ALTER TABLE journal_entries ADD CONSTRAINT chk_credit_positive CHECK (credit_amount >= 0)');
        DB::statement('ALTER TABLE journal_entries ADD CONSTRAINT chk_functional_debit_positive CHECK (functional_debit >= 0)');
        DB::statement('ALTER TABLE journal_entries ADD CONSTRAINT chk_functional_credit_positive CHECK (functional_credit >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};