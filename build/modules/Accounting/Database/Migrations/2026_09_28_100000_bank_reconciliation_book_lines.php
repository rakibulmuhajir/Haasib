<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bank reconciliation against the books.
 *
 * The first design ticked rows of acct.bank_transactions, a feed table nothing ever filled, so
 * there was never anything to reconcile. Now:
 *  - acct.bank_reconciliation_items: the book lines (journal entries on the bank's ledger
 *    account) ticked as cleared in a reconciliation. A line is cleared once, ever.
 *  - acct.bank_statement_lines: the bank's own statement, imported from CSV, each line matched
 *    to the book line it corresponds to (or left unmatched: on the statement, not in the books).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acct.bank_reconciliation_items', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('reconciliation_id');
            $table->uuid('journal_entry_id');
            $table->decimal('amount', 15, 2); // money in +, money out -
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete();
            $table->foreign('reconciliation_id')->references('id')->on('acct.bank_reconciliations')->cascadeOnDelete();
            $table->foreign('journal_entry_id')->references('id')->on('acct.journal_entries')->cascadeOnDelete();
            $table->unique('journal_entry_id');
            $table->index(['company_id', 'reconciliation_id']);
        });

        Schema::create('acct.bank_statement_lines', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('reconciliation_id');
            $table->unsignedInteger('line_number');
            $table->date('line_date');
            $table->string('description', 500)->nullable();
            $table->string('reference', 100)->nullable();
            $table->decimal('amount', 15, 2); // money in +, money out -
            $table->decimal('balance', 15, 2)->nullable();
            $table->uuid('journal_entry_id')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete();
            $table->foreign('reconciliation_id')->references('id')->on('acct.bank_reconciliations')->cascadeOnDelete();
            $table->foreign('journal_entry_id')->references('id')->on('acct.journal_entries')->nullOnDelete();
            $table->index(['company_id', 'reconciliation_id']);
        });

        foreach (['bank_reconciliation_items', 'bank_statement_lines'] as $name) {
            DB::statement("ALTER TABLE acct.{$name} ENABLE ROW LEVEL SECURITY");
            DB::statement("
                CREATE POLICY {$name}_policy ON acct.{$name}
                FOR ALL USING (
                    company_id = current_setting('app.current_company_id', true)::uuid
                    OR current_setting('app.is_super_admin', true)::boolean = true
                )
            ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('acct.bank_statement_lines');
        Schema::dropIfExists('acct.bank_reconciliation_items');
    }
};
