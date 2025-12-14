<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // AR: customers.ar_account_id, invoices.transaction_id, payments.deposit_account_id/transaction_id, credit_notes.transaction_id
        Schema::table('acct.customers', function (Blueprint $table) {
            if (!Schema::hasColumn('acct.customers', 'ar_account_id')) {
                $table->uuid('ar_account_id')->nullable()->after('credit_limit');
                $table->foreign('ar_account_id')->references('id')->on('acct.accounts')->nullOnDelete()->cascadeOnUpdate();
                $table->index('ar_account_id');
            }
        });

        Schema::table('acct.invoice_line_items', function (Blueprint $table) {
            if (!Schema::hasColumn('acct.invoice_line_items', 'income_account_id')) {
                $table->uuid('income_account_id')->nullable()->after('tax_rate');
                $table->foreign('income_account_id')->references('id')->on('acct.accounts')->nullOnDelete()->cascadeOnUpdate();
            }
        });

        Schema::table('acct.invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('acct.invoices', 'transaction_id')) {
                $table->uuid('transaction_id')->nullable()->after('recurring_schedule_id');
                $table->foreign('transaction_id')->references('id')->on('acct.transactions')->nullOnDelete()->cascadeOnUpdate();
                $table->index('transaction_id');
            }
        });

        Schema::table('acct.payments', function (Blueprint $table) {
            if (!Schema::hasColumn('acct.payments', 'deposit_account_id')) {
                $table->uuid('deposit_account_id')->nullable()->after('payment_method');
                $table->foreign('deposit_account_id')->references('id')->on('acct.accounts')->nullOnDelete()->cascadeOnUpdate();
            }
            if (!Schema::hasColumn('acct.payments', 'transaction_id')) {
                $table->uuid('transaction_id')->nullable()->after('deposit_account_id');
                $table->foreign('transaction_id')->references('id')->on('acct.transactions')->nullOnDelete()->cascadeOnUpdate();
                $table->index('transaction_id');
            }
        });

        Schema::table('acct.credit_notes', function (Blueprint $table) {
            if (!Schema::hasColumn('acct.credit_notes', 'transaction_id')) {
                $table->uuid('transaction_id')->nullable()->after('invoice_id');
                $table->foreign('transaction_id')->references('id')->on('acct.transactions')->nullOnDelete()->cascadeOnUpdate();
                $table->index('transaction_id');
            }
        });

        // AP: vendors.ap_account_id, bill_line_items.expense_account_id, bills.transaction_id, bill_payments.payment_account_id/transaction_id, vendor_credits.transaction_id
        Schema::table('acct.vendors', function (Blueprint $table) {
            if (!Schema::hasColumn('acct.vendors', 'ap_account_id')) {
                $table->uuid('ap_account_id')->nullable()->after('account_number');
                $table->foreign('ap_account_id')->references('id')->on('acct.accounts')->nullOnDelete()->cascadeOnUpdate();
                $table->index('ap_account_id');
            }
        });

        Schema::table('acct.bill_line_items', function (Blueprint $table) {
            if (!Schema::hasColumn('acct.bill_line_items', 'expense_account_id')) {
                $table->uuid('expense_account_id')->nullable()->after('tax_rate');
                $table->foreign('expense_account_id')->references('id')->on('acct.accounts')->nullOnDelete()->cascadeOnUpdate();
            }
        });

        Schema::table('acct.bills', function (Blueprint $table) {
            if (!Schema::hasColumn('acct.bills', 'transaction_id')) {
                $table->uuid('transaction_id')->nullable()->after('recurring_schedule_id');
                $table->foreign('transaction_id')->references('id')->on('acct.transactions')->nullOnDelete()->cascadeOnUpdate();
                $table->index('transaction_id');
            }
        });

        Schema::table('acct.bill_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('acct.bill_payments', 'payment_account_id')) {
                $table->uuid('payment_account_id')->nullable()->after('payment_method');
                $table->foreign('payment_account_id')->references('id')->on('acct.accounts')->nullOnDelete()->cascadeOnUpdate();
            }
            if (!Schema::hasColumn('acct.bill_payments', 'transaction_id')) {
                $table->uuid('transaction_id')->nullable()->after('payment_account_id');
                $table->foreign('transaction_id')->references('id')->on('acct.transactions')->nullOnDelete()->cascadeOnUpdate();
                $table->index('transaction_id');
            }
        });

        Schema::table('acct.vendor_credits', function (Blueprint $table) {
            if (!Schema::hasColumn('acct.vendor_credits', 'transaction_id')) {
                $table->uuid('transaction_id')->nullable()->after('vendor_id');
                $table->foreign('transaction_id')->references('id')->on('acct.transactions')->nullOnDelete()->cascadeOnUpdate();
                $table->index('transaction_id');
            }
            if (!Schema::hasColumn('acct.vendor_credits', 'ap_account_id')) {
                $table->uuid('ap_account_id')->nullable()->after('vendor_id');
                $table->foreign('ap_account_id')->references('id')->on('acct.accounts')->nullOnDelete()->cascadeOnUpdate();
                $table->index('ap_account_id');
            }
        });

        Schema::table('acct.vendor_credit_items', function (Blueprint $table) {
            if (!Schema::hasColumn('acct.vendor_credit_items', 'expense_account_id')) {
                $table->uuid('expense_account_id')->nullable()->after('tax_amount');
                $table->foreign('expense_account_id')->references('id')->on('acct.accounts')->nullOnDelete()->cascadeOnUpdate();
            }
        });
    }

    public function down(): void
    {
        Schema::table('acct.vendor_credits', function (Blueprint $table) {
            if (Schema::hasColumn('acct.vendor_credits', 'ap_account_id')) {
                $table->dropForeign(['ap_account_id']);
                $table->dropColumn('ap_account_id');
            }
            if (Schema::hasColumn('acct.vendor_credits', 'transaction_id')) {
                $table->dropForeign(['transaction_id']);
                $table->dropColumn('transaction_id');
            }
        });

        Schema::table('acct.vendor_credit_items', function (Blueprint $table) {
            if (Schema::hasColumn('acct.vendor_credit_items', 'expense_account_id')) {
                $table->dropForeign(['expense_account_id']);
                $table->dropColumn('expense_account_id');
            }
        });

        Schema::table('acct.bill_payments', function (Blueprint $table) {
            if (Schema::hasColumn('acct.bill_payments', 'transaction_id')) {
                $table->dropForeign(['transaction_id']);
                $table->dropColumn('transaction_id');
            }
            if (Schema::hasColumn('acct.bill_payments', 'payment_account_id')) {
                $table->dropForeign(['payment_account_id']);
                $table->dropColumn('payment_account_id');
            }
        });

        Schema::table('acct.bills', function (Blueprint $table) {
            if (Schema::hasColumn('acct.bills', 'transaction_id')) {
                $table->dropForeign(['transaction_id']);
                $table->dropColumn('transaction_id');
            }
        });

        Schema::table('acct.bill_line_items', function (Blueprint $table) {
            if (Schema::hasColumn('acct.bill_line_items', 'expense_account_id')) {
                $table->dropForeign(['expense_account_id']);
                $table->dropColumn('expense_account_id');
            }
        });

        Schema::table('acct.vendors', function (Blueprint $table) {
            if (Schema::hasColumn('acct.vendors', 'ap_account_id')) {
                $table->dropForeign(['ap_account_id']);
                $table->dropColumn('ap_account_id');
            }
        });

        Schema::table('acct.credit_notes', function (Blueprint $table) {
            if (Schema::hasColumn('acct.credit_notes', 'transaction_id')) {
                $table->dropForeign(['transaction_id']);
                $table->dropColumn('transaction_id');
            }
        });

        Schema::table('acct.payments', function (Blueprint $table) {
            if (Schema::hasColumn('acct.payments', 'transaction_id')) {
                $table->dropForeign(['transaction_id']);
                $table->dropColumn('transaction_id');
            }
            if (Schema::hasColumn('acct.payments', 'deposit_account_id')) {
                $table->dropForeign(['deposit_account_id']);
                $table->dropColumn('deposit_account_id');
            }
        });

        Schema::table('acct.invoices', function (Blueprint $table) {
            if (Schema::hasColumn('acct.invoices', 'transaction_id')) {
                $table->dropForeign(['transaction_id']);
                $table->dropColumn('transaction_id');
            }
        });

        Schema::table('acct.invoice_line_items', function (Blueprint $table) {
            if (Schema::hasColumn('acct.invoice_line_items', 'income_account_id')) {
                $table->dropForeign(['income_account_id']);
                $table->dropColumn('income_account_id');
            }
        });

        Schema::table('acct.customers', function (Blueprint $table) {
            if (Schema::hasColumn('acct.customers', 'ar_account_id')) {
                $table->dropForeign(['ar_account_id']);
                $table->dropColumn('ar_account_id');
            }
        });
    }
};
