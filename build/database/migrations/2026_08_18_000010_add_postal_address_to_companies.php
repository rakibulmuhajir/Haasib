<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Give a company a postal address.
 *
 * Every document this application produces -- invoice, bill, credit note,
 * voucher -- carries a letterhead naming who issued it, and until now that
 * letterhead could print a name and a logo and nothing else, because the
 * company record held nothing else. Customers and vendors have had structured
 * addresses all along; the company sending the document did not.
 *
 * Stored as jsonb in the same shape as `acct.customers.billing_address` so one
 * address renderer serves every party on a document rather than one per side.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auth.companies', function (Blueprint $table) {
            $table->jsonb('address')->nullable()->after('country_id');
        });
    }

    public function down(): void
    {
        Schema::table('auth.companies', function (Blueprint $table) {
            $table->dropColumn('address');
        });
    }
};
