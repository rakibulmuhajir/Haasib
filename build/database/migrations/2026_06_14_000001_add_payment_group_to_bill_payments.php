<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('acct.bill_payments', 'payment_group_id')) {
            Schema::table('acct.bill_payments', function (Blueprint $table) {
                $table->uuid('payment_group_id')->nullable()->after('vendor_id');
                $table->string('payment_group_number', 50)->nullable()->after('payment_group_id');
                $table->index('payment_group_id');
                $table->index(['company_id', 'payment_group_number']);
            });
        }

        DB::statement("
            UPDATE acct.bill_payments
            SET payment_group_id = id,
                payment_group_number = payment_number
            WHERE payment_group_id IS NULL
        ");
    }

    public function down(): void
    {
        if (Schema::hasColumn('acct.bill_payments', 'payment_group_id')) {
            Schema::table('acct.bill_payments', function (Blueprint $table) {
                $table->dropIndex(['payment_group_id']);
                $table->dropIndex(['company_id', 'payment_group_number']);
                $table->dropColumn(['payment_group_id', 'payment_group_number']);
            });
        }
    }
};
