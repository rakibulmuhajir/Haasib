<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('umrah.visa_groups', function (Blueprint $table) {
            $table->uuid('sale_transaction_id')->nullable()->after('notes');
            $table->uuid('cost_transaction_id')->nullable()->after('sale_transaction_id');

            $table->foreign('sale_transaction_id')->references('id')->on('acct.transactions')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('cost_transaction_id')->references('id')->on('acct.transactions')->nullOnDelete()->cascadeOnUpdate();
            $table->index('sale_transaction_id');
            $table->index('cost_transaction_id');
        });

        Schema::table('umrah.group_payments', function (Blueprint $table) {
            $table->uuid('transaction_id')->nullable()->after('notes');

            $table->foreign('transaction_id')->references('id')->on('acct.transactions')->nullOnDelete()->cascadeOnUpdate();
            $table->index('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('umrah.group_payments', function (Blueprint $table) {
            $table->dropForeign(['transaction_id']);
            $table->dropIndex(['transaction_id']);
            $table->dropColumn('transaction_id');
        });

        Schema::table('umrah.visa_groups', function (Blueprint $table) {
            $table->dropForeign(['sale_transaction_id']);
            $table->dropForeign(['cost_transaction_id']);
            $table->dropIndex(['sale_transaction_id']);
            $table->dropIndex(['cost_transaction_id']);
            $table->dropColumn(['sale_transaction_id', 'cost_transaction_id']);
        });
    }
};
