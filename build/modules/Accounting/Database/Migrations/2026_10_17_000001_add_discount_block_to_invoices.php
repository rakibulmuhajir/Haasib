<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "No discount" on a single invoice: whoever may give discounts can stop one invoice from
 * getting any, with a reason. A label on the invoice, no money: blocking never changes its
 * amounts. acct.invoices already carries RLS on company_id; new columns inherit it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acct.invoices', function (Blueprint $table) {
            $table->boolean('discount_blocked')->default(false);
            $table->string('discount_block_reason', 255)->nullable();
            $table->uuid('discount_blocked_by_user_id')->nullable();
            $table->timestampTz('discount_blocked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('acct.invoices', function (Blueprint $table) {
            $table->dropColumn(['discount_blocked', 'discount_block_reason', 'discount_blocked_by_user_id', 'discount_blocked_at']);
        });
    }
};
