<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds onboarding and configuration fields to companies table.
     * This migration must run AFTER the companies table is created.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE auth.companies ADD COLUMN IF NOT EXISTS industry_code VARCHAR(50)");
        DB::statement("ALTER TABLE auth.companies ADD COLUMN IF NOT EXISTS registration_number VARCHAR(100)");
        DB::statement("ALTER TABLE auth.companies ADD COLUMN IF NOT EXISTS trade_name VARCHAR(255)");
        DB::statement("ALTER TABLE auth.companies ADD COLUMN IF NOT EXISTS timezone VARCHAR(50) DEFAULT 'UTC'");
        DB::statement("ALTER TABLE auth.companies ADD COLUMN IF NOT EXISTS fiscal_year_start_month INTEGER DEFAULT 1");
        DB::statement("ALTER TABLE auth.companies ADD COLUMN IF NOT EXISTS period_frequency VARCHAR(20) DEFAULT 'monthly'");
        DB::statement("ALTER TABLE auth.companies ADD COLUMN IF NOT EXISTS invoice_prefix VARCHAR(20) DEFAULT 'INV-'");
        DB::statement("ALTER TABLE auth.companies ADD COLUMN IF NOT EXISTS invoice_start_number INTEGER DEFAULT 1001");
        DB::statement("ALTER TABLE auth.companies ADD COLUMN IF NOT EXISTS bill_prefix VARCHAR(20) DEFAULT 'BILL-'");
        DB::statement("ALTER TABLE auth.companies ADD COLUMN IF NOT EXISTS bill_start_number INTEGER DEFAULT 1001");
        DB::statement("ALTER TABLE auth.companies ADD COLUMN IF NOT EXISTS default_customer_payment_terms INTEGER DEFAULT 30");
        DB::statement("ALTER TABLE auth.companies ADD COLUMN IF NOT EXISTS default_vendor_payment_terms INTEGER DEFAULT 30");
        DB::statement("ALTER TABLE auth.companies ADD COLUMN IF NOT EXISTS tax_registered BOOLEAN DEFAULT false");
        DB::statement("ALTER TABLE auth.companies ADD COLUMN IF NOT EXISTS tax_rate DECIMAL(5,2)");
        DB::statement("ALTER TABLE auth.companies ADD COLUMN IF NOT EXISTS tax_inclusive BOOLEAN DEFAULT false");
        DB::statement("ALTER TABLE auth.companies ADD COLUMN IF NOT EXISTS onboarding_completed BOOLEAN DEFAULT false");
        DB::statement("ALTER TABLE auth.companies ADD COLUMN IF NOT EXISTS onboarding_completed_at TIMESTAMP");

        // Add default account references
        DB::statement("ALTER TABLE auth.companies ADD COLUMN IF NOT EXISTS ar_account_id UUID");
        DB::statement("ALTER TABLE auth.companies ADD COLUMN IF NOT EXISTS ap_account_id UUID");
        DB::statement("ALTER TABLE auth.companies ADD COLUMN IF NOT EXISTS income_account_id UUID");
        DB::statement("ALTER TABLE auth.companies ADD COLUMN IF NOT EXISTS expense_account_id UUID");
        DB::statement("ALTER TABLE auth.companies ADD COLUMN IF NOT EXISTS bank_account_id UUID");
        DB::statement("ALTER TABLE auth.companies ADD COLUMN IF NOT EXISTS retained_earnings_account_id UUID");
        DB::statement("ALTER TABLE auth.companies ADD COLUMN IF NOT EXISTS sales_tax_payable_account_id UUID");
        DB::statement("ALTER TABLE auth.companies ADD COLUMN IF NOT EXISTS purchase_tax_receivable_account_id UUID");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE auth.companies DROP COLUMN IF EXISTS industry_code");
        DB::statement("ALTER TABLE auth.companies DROP COLUMN IF EXISTS registration_number");
        DB::statement("ALTER TABLE auth.companies DROP COLUMN IF EXISTS trade_name");
        DB::statement("ALTER TABLE auth.companies DROP COLUMN IF EXISTS timezone");
        DB::statement("ALTER TABLE auth.companies DROP COLUMN IF EXISTS fiscal_year_start_month");
        DB::statement("ALTER TABLE auth.companies DROP COLUMN IF EXISTS period_frequency");
        DB::statement("ALTER TABLE auth.companies DROP COLUMN IF EXISTS invoice_prefix");
        DB::statement("ALTER TABLE auth.companies DROP COLUMN IF EXISTS invoice_start_number");
        DB::statement("ALTER TABLE auth.companies DROP COLUMN IF EXISTS bill_prefix");
        DB::statement("ALTER TABLE auth.companies DROP COLUMN IF EXISTS bill_start_number");
        DB::statement("ALTER TABLE auth.companies DROP COLUMN IF EXISTS default_customer_payment_terms");
        DB::statement("ALTER TABLE auth.companies DROP COLUMN IF EXISTS default_vendor_payment_terms");
        DB::statement("ALTER TABLE auth.companies DROP COLUMN IF EXISTS tax_registered");
        DB::statement("ALTER TABLE auth.companies DROP COLUMN IF EXISTS tax_rate");
        DB::statement("ALTER TABLE auth.companies DROP COLUMN IF EXISTS tax_inclusive");
        DB::statement("ALTER TABLE auth.companies DROP COLUMN IF EXISTS onboarding_completed");
        DB::statement("ALTER TABLE auth.companies DROP COLUMN IF EXISTS onboarding_completed_at");
        DB::statement("ALTER TABLE auth.companies DROP COLUMN IF EXISTS ar_account_id");
        DB::statement("ALTER TABLE auth.companies DROP COLUMN IF EXISTS ap_account_id");
        DB::statement("ALTER TABLE auth.companies DROP COLUMN IF EXISTS income_account_id");
        DB::statement("ALTER TABLE auth.companies DROP COLUMN IF EXISTS expense_account_id");
        DB::statement("ALTER TABLE auth.companies DROP COLUMN IF EXISTS bank_account_id");
        DB::statement("ALTER TABLE auth.companies DROP COLUMN IF EXISTS retained_earnings_account_id");
        DB::statement("ALTER TABLE auth.companies DROP COLUMN IF EXISTS sales_tax_payable_account_id");
        DB::statement("ALTER TABLE auth.companies DROP COLUMN IF EXISTS purchase_tax_receivable_account_id");
    }
};
