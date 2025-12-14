<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * PostgreSQL does not support DEFERRABLE CHECK constraints.
     * We must remove the CHECK constraint and validate transaction balance
     * in the application layer instead (GlPostingService).
     *
     * The trigger still automatically maintains total_debit/total_credit,
     * but validation happens in code before commit.
     */
    public function up(): void
    {
        // Drop the check constraint that causes immediate validation
        DB::statement('ALTER TABLE acct.transactions DROP CONSTRAINT IF EXISTS transactions_balance_chk');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate the immediate check constraint
        DB::statement("
            ALTER TABLE acct.transactions
            ADD CONSTRAINT transactions_balance_chk
            CHECK (total_debit = total_credit AND total_debit >= 0 AND total_credit >= 0)
        ");
    }
};
