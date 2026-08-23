<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * acct.credit_notes stores only base_currency -- no currency, no
 * exchange_rate -- so it cannot represent a credit against a
 * foreign-currency invoice, which a ticket cancellation needs whenever the
 * sale was not in the company's base currency. acct.vendor_credits already
 * carries this dual-recording shape; this brings credit_notes to parity
 * with it.
 *
 * Existing rows predate these columns and are base-currency by
 * definition, so they are backfilled before the CHECK constraint is
 * added -- the constraint would otherwise reject every one of them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acct.credit_notes', function (Blueprint $table) {
            $table->char('currency', 3)->nullable()->after('amount');
            $table->decimal('exchange_rate', 18, 8)->nullable()->after('currency');
            $table->decimal('base_amount', 15, 2)->nullable()->after('base_currency');
        });

        DB::statement('UPDATE acct.credit_notes SET currency = base_currency, base_amount = amount WHERE currency IS NULL');

        DB::statement("
            ALTER TABLE acct.credit_notes
            ADD CONSTRAINT credit_notes_exchange_rate_check
            CHECK (
                (currency = base_currency AND exchange_rate IS NULL AND base_amount = round(amount, 2))
                OR (currency <> base_currency AND exchange_rate > 0 AND base_amount = round(amount * exchange_rate, 2))
            )
        ");

        Schema::table('acct.credit_notes', function (Blueprint $table) {
            $table->foreign('currency')->references('code')->on('public.currencies')->cascadeOnUpdate();
            $table->foreign('base_currency')->references('code')->on('public.currencies')->cascadeOnUpdate();
        });

        Schema::table('acct.credit_note_items', function (Blueprint $table) {
            $table->uuid('income_account_id')->nullable()->after('total');
            $table->foreign('income_account_id')->references('id')->on('acct.accounts')->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('acct.credit_note_items', function (Blueprint $table) {
            if (Schema::hasColumn('acct.credit_note_items', 'income_account_id')) {
                $table->dropForeign(['income_account_id']);
                $table->dropColumn('income_account_id');
            }
        });

        Schema::table('acct.credit_notes', function (Blueprint $table) {
            $table->dropForeign(['currency']);
            $table->dropForeign(['base_currency']);
        });

        DB::statement('ALTER TABLE acct.credit_notes DROP CONSTRAINT IF EXISTS credit_notes_exchange_rate_check');

        Schema::table('acct.credit_notes', function (Blueprint $table) {
            $table->dropColumn(['currency', 'exchange_rate', 'base_amount']);
        });
    }
};
