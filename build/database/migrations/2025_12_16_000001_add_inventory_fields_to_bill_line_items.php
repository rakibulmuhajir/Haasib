<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add item_id and warehouse_id to bill_line_items for inventory integration.
     * When a bill line has an item_id, receiving the bill will create stock movements.
     */
    public function up(): void
    {
        Schema::table('acct.bill_line_items', function (Blueprint $table) {
            $table->uuid('item_id')->nullable()->after('bill_id');
            $table->uuid('warehouse_id')->nullable()->after('item_id');

            // Foreign keys - only add if the inv schema tables exist
            if (Schema::hasTable('inv.items')) {
                $table->foreign('item_id')
                    ->references('id')->on('inv.items')
                    ->nullOnDelete()->cascadeOnUpdate();
            }

            if (Schema::hasTable('inv.warehouses')) {
                $table->foreign('warehouse_id')
                    ->references('id')->on('inv.warehouses')
                    ->nullOnDelete()->cascadeOnUpdate();
            }

            $table->index(['item_id']);
            $table->index(['warehouse_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('acct.bill_line_items', function (Blueprint $table) {
            $table->dropForeign(['item_id']);
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn(['item_id', 'warehouse_id']);
        });
    }
};
