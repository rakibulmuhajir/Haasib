<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('inv.stock_movements', 'gl_transaction_id')) {
            Schema::table('inv.stock_movements', function (Blueprint $table) {
                $table->uuid('gl_transaction_id')->nullable()->after('total_cost');
                $table->foreign('gl_transaction_id')
                    ->references('id')->on('acct.transactions')
                    ->nullOnDelete()->cascadeOnUpdate();
                $table->index('gl_transaction_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('inv.stock_movements', 'gl_transaction_id')) {
            Schema::table('inv.stock_movements', function (Blueprint $table) {
                $table->dropForeign(['gl_transaction_id']);
                $table->dropIndex(['gl_transaction_id']);
                $table->dropColumn('gl_transaction_id');
            });
        }
    }
};
