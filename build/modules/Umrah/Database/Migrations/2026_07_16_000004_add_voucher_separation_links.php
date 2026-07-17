<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('umrah.vouchers', function (Blueprint $table) {
            $table->uuid('source_voucher_id')->nullable()->after('agent_id');
            $table->uuid('billing_voucher_id')->nullable()->after('source_voucher_id');

            $table->foreign('source_voucher_id')->references('id')->on('umrah.vouchers')->nullOnDelete();
            $table->foreign('billing_voucher_id')->references('id')->on('umrah.vouchers')->nullOnDelete();
            $table->index(['company_id', 'source_voucher_id']);
            $table->index(['company_id', 'billing_voucher_id']);
        });
    }

    public function down(): void
    {
        Schema::table('umrah.vouchers', function (Blueprint $table) {
            $table->dropForeign(['source_voucher_id']);
            $table->dropForeign(['billing_voucher_id']);
            $table->dropIndex(['company_id', 'source_voucher_id']);
            $table->dropIndex(['company_id', 'billing_voucher_id']);
            $table->dropColumn(['source_voucher_id', 'billing_voucher_id']);
        });
    }
};
