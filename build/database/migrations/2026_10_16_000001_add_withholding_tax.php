<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Withholding tax (WHT). No new tables: every table touched already has row-level security
 * on company_id, and added columns inherit it.
 *
 *  - acct.tax_rates          section, non-filer rate, which side the rate is for
 *  - acct.vendors/customers  filer status and a default WHT rate
 *  - acct.bill_payments/payments  what was withheld, snapshotted at posting time
 *  - acct.company_tax_settings    the two ledger accounts WHT posts to
 *
 * No rate numbers are seeded: rates change every budget and the owner enters them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acct.tax_rates', function (Blueprint $table) {
            $table->string('section', 50)->nullable()->after('tax_type');
            $table->decimal('rate_non_filer', 8, 4)->nullable()->after('rate');
            $table->string('applies_to', 20)->default('both')->after('section');
        });
        DB::statement("ALTER TABLE acct.tax_rates ADD CONSTRAINT tax_rates_applies_to_chk CHECK (applies_to IN ('payments_made', 'payments_received', 'both'))");
        DB::statement('ALTER TABLE acct.tax_rates ADD CONSTRAINT tax_rates_rate_non_filer_chk CHECK (rate_non_filer IS NULL OR (rate_non_filer >= 0 AND rate_non_filer <= 100))');

        foreach (['acct.vendors', 'acct.customers'] as $party) {
            Schema::table($party, function (Blueprint $table) {
                $table->string('filer_status', 20)->default('unknown');
                $table->uuid('default_wht_rate_id')->nullable();
                $table->foreign('default_wht_rate_id')->references('id')->on('acct.tax_rates')->nullOnDelete();
            });
            $name = str_replace('acct.', '', $party);
            DB::statement("ALTER TABLE {$party} ADD CONSTRAINT {$name}_filer_status_chk CHECK (filer_status IN ('filer', 'non_filer', 'unknown'))");
        }

        foreach (['acct.bill_payments', 'acct.payments'] as $payments) {
            Schema::table($payments, function (Blueprint $table) {
                $table->uuid('wht_rate_id')->nullable();
                $table->decimal('wht_rate', 8, 4)->nullable();      // the % actually applied
                $table->decimal('wht_amount', 15, 2)->default(0);   // base currency
                $table->string('wht_section', 50)->nullable();
                $table->string('wht_filer_status', 20)->nullable(); // the party's status when it was applied
                $table->foreign('wht_rate_id')->references('id')->on('acct.tax_rates')->nullOnDelete();
            });
        }

        Schema::table('acct.payments', function (Blueprint $table) {
            // The number on the certificate the customer issued for what they deducted.
            $table->string('wht_certificate_number', 100)->nullable();
        });

        Schema::table('acct.company_tax_settings', function (Blueprint $table) {
            $table->uuid('wht_payable_account_id')->nullable();
            $table->uuid('advance_tax_account_id')->nullable();
            $table->foreign('wht_payable_account_id')->references('id')->on('acct.accounts')->nullOnDelete();
            $table->foreign('advance_tax_account_id')->references('id')->on('acct.accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('acct.company_tax_settings', function (Blueprint $table) {
            $table->dropForeign(['wht_payable_account_id']);
            $table->dropForeign(['advance_tax_account_id']);
            $table->dropColumn(['wht_payable_account_id', 'advance_tax_account_id']);
        });

        Schema::table('acct.payments', function (Blueprint $table) {
            $table->dropColumn('wht_certificate_number');
        });

        foreach (['acct.bill_payments', 'acct.payments'] as $payments) {
            Schema::table($payments, function (Blueprint $table) {
                $table->dropForeign(['wht_rate_id']);
                $table->dropColumn(['wht_rate_id', 'wht_rate', 'wht_amount', 'wht_section', 'wht_filer_status']);
            });
        }

        foreach (['acct.vendors', 'acct.customers'] as $party) {
            $name = str_replace('acct.', '', $party);
            DB::statement("ALTER TABLE {$party} DROP CONSTRAINT IF EXISTS {$name}_filer_status_chk");
            Schema::table($party, function (Blueprint $table) {
                $table->dropForeign(['default_wht_rate_id']);
                $table->dropColumn(['filer_status', 'default_wht_rate_id']);
            });
        }

        DB::statement('ALTER TABLE acct.tax_rates DROP CONSTRAINT IF EXISTS tax_rates_rate_non_filer_chk');
        DB::statement('ALTER TABLE acct.tax_rates DROP CONSTRAINT IF EXISTS tax_rates_applies_to_chk');
        Schema::table('acct.tax_rates', function (Blueprint $table) {
            $table->dropColumn(['section', 'rate_non_filer', 'applies_to']);
        });
    }
};
