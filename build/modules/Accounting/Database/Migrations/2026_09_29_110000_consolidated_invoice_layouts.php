<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Consolidated invoice layouts: which of the standard columns a document leaves out, and each
 * customer's last layout (columns dropped, custom columns used, title) so the next one starts
 * the same way. Adding a column fires no row trigger, so the "never changed" rule on saved
 * documents is untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE acct.customers ADD COLUMN IF NOT EXISTS invoice_layout jsonb NULL");
        DB::statement("ALTER TABLE acct.consolidated_invoices ADD COLUMN IF NOT EXISTS hidden_columns jsonb NOT NULL DEFAULT '[]'::jsonb");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE acct.consolidated_invoices DROP COLUMN IF EXISTS hidden_columns');
        DB::statement('ALTER TABLE acct.customers DROP COLUMN IF EXISTS invoice_layout');
    }
};
