<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop all application tables except users and system tables

        // Auth schema tables
        Schema::dropIfExists('auth.companies');
        Schema::dropIfExists('auth.company_user');

        // Ledger schema tables
        Schema::dropIfExists('ledger.chart_of_accounts');
        Schema::dropIfExists('ledger.journal_entries');
        Schema::dropIfExists('ledger.journal_lines');
        Schema::dropIfExists('ledger.accounts_receivable');

        // App schema tables
        Schema::dropIfExists('app.languages');
        Schema::dropIfExists('app.currencies');
        Schema::dropIfExists('app.countries');
        Schema::dropIfExists('app.locales');
        Schema::dropIfExists('app.country_language');
        Schema::dropIfExists('app.country_currency');
        Schema::dropIfExists('app.customers');
        Schema::dropIfExists('app.vendors');
        Schema::dropIfExists('app.contacts');
        Schema::dropIfExists('app.interactions');
        Schema::dropIfExists('app.user_accounts');
        Schema::dropIfExists('app.fiscal_years');
        Schema::dropIfExists('app.accounting_periods');
        Schema::dropIfExists('app.transactions');
        Schema::dropIfExists('app.invoices');
        Schema::dropIfExists('app.invoice_items');
        Schema::dropIfExists('app.invoice_item_taxes');
        Schema::dropIfExists('app.payments');
        Schema::dropIfExists('app.payment_allocations');
        Schema::dropIfExists('app.items');
        Schema::dropIfExists('app.exchange_rates');
        Schema::dropIfExists('app.company_secondary_currencies');

        // Command overlays table
        Schema::dropIfExists('command_overlays');

        // Audit logs
        Schema::dropIfExists('audit_logs');

        // Idempotency keys
        Schema::dropIfExists('idempotency_keys');

        // Drop schemas if they exist and are empty
        DB::statement('DROP SCHEMA IF EXISTS auth CASCADE');
        DB::statement('DROP SCHEMA IF EXISTS ledger CASCADE');
        DB::statement('DROP SCHEMA IF EXISTS app CASCADE');
    }

    public function down(): void
    {
        // This migration cannot be reversed
        // All dropped tables and data would need to be recreated from scratch
    }
};
