<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add goods receipt tracking fields.
     *
     * Separates "receiving a bill" (document/accounting) from
     * "receiving goods" (physical inventory).
     *
     * Flow:
     * 1. Bill created (draft)
     * 2. Bill received (AP posted) - received_at
     * 3. Bill paid (payment made) - paid_at
     * 4. Goods received (stock increases) - goods_received_at
     *
     * Steps 2-4 can happen in any order depending on business terms.
     */
    public function up(): void
    {
        // Add goods_received_at to bills
        Schema::table('acct.bills', function (Blueprint $table) {
            $table->timestamp('goods_received_at')->nullable()->after('received_at');
        });

        // Add quantity_received to bill_line_items for partial receipt tracking
        Schema::table('acct.bill_line_items', function (Blueprint $table) {
            $table->decimal('quantity_received', 10, 2)->default(0)->after('quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('acct.bills', function (Blueprint $table) {
            $table->dropColumn('goods_received_at');
        });

        Schema::table('acct.bill_line_items', function (Blueprint $table) {
            $table->dropColumn('quantity_received');
        });
    }
};
