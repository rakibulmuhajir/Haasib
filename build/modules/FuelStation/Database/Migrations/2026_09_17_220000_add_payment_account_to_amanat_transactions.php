<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fuel.amanat_transactions', function (Blueprint $table) {
            $table->uuid('payment_account_id')->nullable()->after('amount');
            $table->foreign('payment_account_id')
                ->references('id')->on('acct.accounts')
                ->nullOnDelete();
            $table->index(['company_id', 'payment_account_id']);
        });
    }

    public function down(): void
    {
        Schema::table('fuel.amanat_transactions', function (Blueprint $table) {
            $table->dropForeign(['payment_account_id']);
            $table->dropIndex(['company_id', 'payment_account_id']);
            $table->dropColumn('payment_account_id');
        });
    }
};
