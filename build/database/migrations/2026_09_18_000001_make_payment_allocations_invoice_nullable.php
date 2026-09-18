<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A payment allocation used to always name an invoice, so a payment could only ever be
 * recorded against exactly one invoice for exactly the amount still owed on it. That made
 * two ordinary real-life situations impossible to enter: a buyer handing over a lump sum
 * that happens to cover several outstanding invoices at once, and a buyer paying in advance
 * of any invoice at all.
 *
 * This drops the NOT NULL on invoice_id so a payment_allocations row can now name either an
 * invoice (money applied) or nothing (money sitting on account, unapplied). The row still
 * belongs to exactly one payment and carries the amount, so acct.payments' own total stays
 * the single source of truth for how much cash moved; payment_allocations rows say where
 * each slice of it went. Applying an on-account credit to a later invoice (see
 * Payment\ApplyCreditAction) reduces the null-invoice row and creates/extends an
 * invoice-linked one for the same payment - a subsidiary reclass, not a new payment.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE acct.payment_allocations ALTER COLUMN invoice_id DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DELETE FROM acct.payment_allocations WHERE invoice_id IS NULL');
        DB::statement('ALTER TABLE acct.payment_allocations ALTER COLUMN invoice_id SET NOT NULL');
    }
};
