<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acct.payments', function (Blueprint $table) {
            $table->decimal('transaction_charge', 18, 6)->default(0)->after('base_amount');
            $table->decimal('base_transaction_charge', 15, 2)->default(0)->after('transaction_charge');
        });

        Schema::table('acct.bill_payments', function (Blueprint $table) {
            $table->decimal('transaction_charge', 18, 6)->default(0)->after('base_amount');
            $table->decimal('base_transaction_charge', 15, 2)->default(0)->after('transaction_charge');
        });
    }

    public function down(): void
    {
        Schema::table('acct.bill_payments', function (Blueprint $table) {
            $table->dropColumn(['transaction_charge', 'base_transaction_charge']);
        });

        Schema::table('acct.payments', function (Blueprint $table) {
            $table->dropColumn(['transaction_charge', 'base_transaction_charge']);
        });
    }
};
