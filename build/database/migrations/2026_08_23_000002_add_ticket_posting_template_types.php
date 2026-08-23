<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ticket postings take every account from a template role rather than
 * from a line's income/expense account, which keeps the account-type
 * validators on invoice and bill lines exactly as strict as they are.
 * That only works if the roles and doc types exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE acct.posting_templates DROP CONSTRAINT posting_templates_doc_type_chk');
        DB::statement("
            ALTER TABLE acct.posting_templates
            ADD CONSTRAINT posting_templates_doc_type_chk
            CHECK (doc_type IN (
                'AR_INVOICE','AR_PAYMENT','AR_CREDIT_NOTE',
                'AP_BILL','AP_PAYMENT','AP_VENDOR_CREDIT',
                'BANK_TRANSFER','BANK_FEE','PAYROLL',
                'TICKET_INVOICE','TICKET_BILL','TICKET_CREDIT_NOTE','TICKET_VENDOR_CREDIT'
            ))
        ");

        DB::statement('ALTER TABLE acct.posting_template_lines DROP CONSTRAINT posting_template_lines_role_chk');
        DB::statement("
            ALTER TABLE acct.posting_template_lines
            ADD CONSTRAINT posting_template_lines_role_chk
            CHECK (role IN (
                'AR','AP','REVENUE','EXPENSE','TAX_PAYABLE','TAX_RECEIVABLE',
                'DISCOUNT_GIVEN','DISCOUNT_RECEIVED','SHIPPING',
                'BANK','CASH','CLEARING','RETAINED_EARNINGS','SUSPENSE',
                'SERVICE_FEE','CANCELLATION_ADJUSTMENT','ROUNDING'
            ))
        ");
    }

    public function down(): void
    {
        DB::statement("DELETE FROM acct.posting_template_lines WHERE role IN ('SERVICE_FEE','CANCELLATION_ADJUSTMENT','ROUNDING')");
        DB::statement("DELETE FROM acct.posting_templates WHERE doc_type LIKE 'TICKET_%'");

        DB::statement('ALTER TABLE acct.posting_templates DROP CONSTRAINT posting_templates_doc_type_chk');
        DB::statement("
            ALTER TABLE acct.posting_templates
            ADD CONSTRAINT posting_templates_doc_type_chk
            CHECK (doc_type IN (
                'AR_INVOICE','AR_PAYMENT','AR_CREDIT_NOTE',
                'AP_BILL','AP_PAYMENT','AP_VENDOR_CREDIT',
                'BANK_TRANSFER','BANK_FEE','PAYROLL'
            ))
        ");

        DB::statement('ALTER TABLE acct.posting_template_lines DROP CONSTRAINT posting_template_lines_role_chk');
        DB::statement("
            ALTER TABLE acct.posting_template_lines
            ADD CONSTRAINT posting_template_lines_role_chk
            CHECK (role IN (
                'AR','AP','REVENUE','EXPENSE','TAX_PAYABLE','TAX_RECEIVABLE',
                'DISCOUNT_GIVEN','DISCOUNT_RECEIVED','SHIPPING',
                'BANK','CASH','CLEARING','RETAINED_EARNINGS','SUSPENSE'
            ))
        ");
    }
};
