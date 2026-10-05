<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Partner money goes through the books: each partner gets their own Capital and Drawings
 * accounts (capital_account_id beside the existing drawing_account_id), every movement links
 * to the journal it posted (gl_transaction_id), and monthly profit shares are a new
 * partner_transactions type. No new tables, so no new RLS.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE auth.partners ADD COLUMN IF NOT EXISTS capital_account_id uuid NULL');
        DB::statement("DO \$\$ BEGIN
            IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'partners_capital_account_fk') THEN
                ALTER TABLE auth.partners ADD CONSTRAINT partners_capital_account_fk
                    FOREIGN KEY (capital_account_id) REFERENCES acct.accounts(id) ON DELETE SET NULL ON UPDATE CASCADE;
            END IF;
        END \$\$");

        DB::statement('ALTER TABLE auth.partner_transactions ADD COLUMN IF NOT EXISTS gl_transaction_id uuid NULL');
        DB::statement("DO \$\$ BEGIN
            IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'partner_transactions_gl_transaction_fk') THEN
                ALTER TABLE auth.partner_transactions ADD CONSTRAINT partner_transactions_gl_transaction_fk
                    FOREIGN KEY (gl_transaction_id) REFERENCES acct.transactions(id) ON DELETE SET NULL ON UPDATE CASCADE;
            END IF;
        END \$\$");
        DB::statement('CREATE INDEX IF NOT EXISTS partner_transactions_gl_transaction_idx ON auth.partner_transactions (gl_transaction_id)');

        DB::statement('ALTER TABLE auth.partner_transactions DROP CONSTRAINT IF EXISTS partner_transactions_type_check');
        DB::statement("ALTER TABLE auth.partner_transactions ADD CONSTRAINT partner_transactions_type_check
            CHECK (transaction_type IN ('investment', 'withdrawal', 'adjustment', 'profit_share'))");
    }

    public function down(): void
    {
        DB::statement("DELETE FROM auth.partner_transactions WHERE transaction_type = 'profit_share'");
        DB::statement('ALTER TABLE auth.partner_transactions DROP CONSTRAINT IF EXISTS partner_transactions_type_check');
        DB::statement("ALTER TABLE auth.partner_transactions ADD CONSTRAINT partner_transactions_type_check
            CHECK (transaction_type IN ('investment', 'withdrawal', 'adjustment'))");
        DB::statement('DROP INDEX IF EXISTS auth.partner_transactions_gl_transaction_idx');
        DB::statement('ALTER TABLE auth.partner_transactions DROP COLUMN IF EXISTS gl_transaction_id');
        DB::statement('ALTER TABLE auth.partners DROP COLUMN IF EXISTS capital_account_id');
    }
};
