<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Part of a fuel bill's litres can go straight from the supplier's tanker to a
 * customer and never reach the station's tank -- not in the meters, not in the
 * dip. direct_quantity is how much of a tracked line was sold that way; the
 * rest (quantity - direct_quantity) is what receiving actually puts in a tank
 * (see ReceiveGoodsAction) and what the daily close's pendingDeliveries() still
 * expects to see arrive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acct.bill_line_items', function (Blueprint $table) {
            $table->decimal('direct_quantity', 18, 3)->default(0)->after('quantity');
        });

        DB::statement('ALTER TABLE acct.bill_line_items ADD CONSTRAINT bill_line_items_direct_quantity_range CHECK (direct_quantity >= 0 AND direct_quantity <= quantity)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE acct.bill_line_items DROP CONSTRAINT IF EXISTS bill_line_items_direct_quantity_range');

        Schema::table('acct.bill_line_items', function (Blueprint $table) {
            $table->dropColumn('direct_quantity');
        });
    }
};
